<?php

namespace App\Services;

use App\Models\Annotation;
use App\Models\MallMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates and stores shop signs and advertisements. Images arrive as data
 * URLs and are saved as files next to the map; an annotation that sends back
 * the URL it was given keeps its existing image.
 */
class AnnotationStore
{
    private const MAX_IMAGE_BYTES = 1_000_000;

    /**
     * Validation rules for one annotation in the viewer's JSON shape.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'id' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9._-]+$/'],
            $prefix.'kind' => ['required', Rule::in(Annotation::KINDS)],
            $prefix.'floor_code' => ['nullable', 'string', 'max:20'],
            $prefix.'unit_code' => ['nullable', 'string', 'max:40'],
            $prefix.'text' => ['required', 'string', 'max:60'],
            $prefix.'subtitle' => ['nullable', 'string', 'max:80'],
            $prefix.'background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            $prefix.'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            $prefix.'image' => ['nullable', 'string'],
            $prefix.'width_m' => ['required', 'numeric', 'between:0.2,10'],
            $prefix.'lift_m' => ['required', 'numeric', 'between:-5,10'],
            $prefix.'mount' => ['required', Rule::in(Annotation::MOUNTS)],
            $prefix.'position' => ['required', 'array'],
            $prefix.'position.x' => ['required', 'numeric', 'between:-100000,100000'],
            $prefix.'position.y' => ['required', 'numeric', 'between:-100000,100000'],
            $prefix.'position.z' => ['required', 'numeric', 'between:-1000,1000'],
            $prefix.'yaw_deg' => ['required', 'numeric', 'min:0', 'lt:360'],
        ];
    }

    /**
     * Replace every annotation of the map with $items (the viewer's autosave).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<Annotation>
     */
    public function replaceAll(MallMap $map, array $items): array
    {
        $validator = validator(['annotations' => $items], ['annotations' => ['present', 'array', 'max:500']] + self::rules('annotations.*.'));
        $validator->validate();
        $this->checkFloors($map, array_map(fn (array $item): ?string => $item['floor_code'] ?? null, $items), 'annotations');

        return DB::transaction(function () use ($map, $items): array {
            $existing = $map->annotations()->get()->keyBy('code');
            $kept = [];
            $saved = [];
            foreach ($items as $index => $item) {
                $code = $this->freeCode($map, $item['id'] ?? null, $kept);
                $kept[] = $code;
                $current = $existing->get($code);
                $saved[] = $this->write($map, $current ?? new Annotation, $code, $item, "annotations.{$index}.image");
            }
            foreach ($existing as $code => $annotation) {
                if (! in_array($code, $kept, true)) {
                    $this->delete($annotation);
                }
            }

            return $saved;
        });
    }

    /**
     * Create one annotation (API).
     *
     * @param  array<string, mixed>  $item
     */
    public function create(MallMap $map, array $item): Annotation
    {
        $this->checkFloors($map, [$item['floor_code'] ?? null]);

        return DB::transaction(fn (): Annotation => $this->write($map, new Annotation, $this->freeCode($map, null, []), $item, 'image'));
    }

    /**
     * Update one annotation (API).
     *
     * @param  array<string, mixed>  $item
     */
    public function update(Annotation $annotation, array $item): Annotation
    {
        $this->checkFloors($annotation->mallMap, [$item['floor_code'] ?? null]);

        return DB::transaction(fn (): Annotation => $this->write($annotation->mallMap, $annotation, $annotation->code, $item, 'image'));
    }

    public function delete(Annotation $annotation): void
    {
        if ($annotation->image_path !== null) {
            Storage::disk('local')->delete($annotation->image_path);
        }
        $annotation->delete();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function write(MallMap $map, Annotation $annotation, string $code, array $item, string $imageField): Annotation
    {
        $oldImage = $annotation->image_path;
        $image = $item['image'] ?? null;
        $imagePath = $oldImage;
        if ($image === null || $image === '') {
            $imagePath = null;
        } elseif (str_starts_with($image, 'data:')) {
            $imagePath = $this->storeImage($map, $image, $imageField);
        } elseif ($oldImage === null) {
            throw ValidationException::withMessages([$imageField => 'Send a new image as a PNG, JPEG or WebP data URL.']);
        }

        $annotation->fill([
            'code' => $code,
            'kind' => $item['kind'],
            'floor_code' => $item['floor_code'] ?? null,
            'unit_code' => ($item['unit_code'] ?? '') === '' ? null : $item['unit_code'],
            'text' => $item['text'],
            'subtitle' => $item['subtitle'] ?? '',
            'background' => strtolower($item['background']),
            'color' => strtolower($item['color']),
            'image_path' => $imagePath,
            'width_m' => $item['width_m'],
            'lift_m' => $item['lift_m'],
            'mount' => $item['mount'],
            'x_m' => $item['position']['x'],
            'y_m' => $item['position']['y'],
            'z_m' => $item['position']['z'],
            'yaw_deg' => $item['yaw_deg'],
        ]);
        $annotation->mallMap()->associate($map);
        $annotation->save();

        if ($oldImage !== null && $oldImage !== $imagePath) {
            Storage::disk('local')->delete($oldImage);
        }

        return $annotation;
    }

    private function storeImage(MallMap $map, string $dataUrl, string $field): string
    {
        if (! preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $match)) {
            throw ValidationException::withMessages([$field => 'The image must be a PNG, JPEG or WebP data URL.']);
        }
        $bytes = base64_decode($match[2], true);
        if ($bytes === false || strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([$field => 'The image must be smaller than 1 MB.']);
        }
        $info = @getimagesizefromstring($bytes);
        $mime = ['png' => 'image/png', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][$match[1]];
        if ($info === false || $info['mime'] !== $mime) {
            throw ValidationException::withMessages([$field => 'The image data is not a valid picture.']);
        }

        $path = $map->storage_dir.'/images/'.Str::uuid().'.'.($match[1] === 'jpeg' ? 'jpg' : $match[1]);
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    /**
     * @param  list<string>  $taken
     */
    private function freeCode(MallMap $map, ?string $wanted, array $taken): string
    {
        if ($wanted !== null && $wanted !== '' && ! in_array($wanted, $taken, true)) {
            return $wanted;
        }
        $numbers = array_merge(
            $map->annotations()->pluck('code')->all(),
            $taken,
        );
        $highest = 0;
        foreach ($numbers as $code) {
            if (preg_match('/^ann-(\d+)$/', $code, $m)) {
                $highest = max($highest, (int) $m[1]);
            }
        }

        return sprintf('ann-%04d', $highest + 1);
    }

    /**
     * @param  list<string|null>  $floorCodes
     */
    private function checkFloors(MallMap $map, array $floorCodes, string $field = 'floor_code'): void
    {
        foreach ($floorCodes as $index => $code) {
            if ($code !== null && ! in_array($code, $map->floor_codes, true)) {
                $key = $field === 'annotations' ? "annotations.{$index}.floor_code" : 'floor_code';
                throw ValidationException::withMessages([$key => "This map has no floor {$code}."]);
            }
        }
    }
}
