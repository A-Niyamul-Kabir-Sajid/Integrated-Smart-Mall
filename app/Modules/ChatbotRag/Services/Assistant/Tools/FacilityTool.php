<?php

namespace App\Modules\ChatbotRag\Services\Assistant\Tools;

use App\Modules\ChatbotRag\DTOs\GenerationRequestData;
use App\Modules\ChatbotRag\DTOs\SourceSnippetData;
use App\Modules\ChatbotRag\DTOs\ToolResultData;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\ChatbotRag\Services\Assistant\NavigationHandoff;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;
use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Enums\FacilityStatus;
use App\Modules\MallShopDirectory\Enums\FacilityType;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Services\FacilityService;

/**
 * Answers "where is the washroom / ATM / prayer room / lift / exit / information desk" from the
 * facility directory as it is at this moment, including what is temporarily closed.
 */
class FacilityTool
{
    public const LIMIT = 8;

    /**
     * Words and phrases that name a facility type, lower case.
     */
    private const TYPE_WORDS = [
        'toilet' => ['toilet', 'toilets', 'washroom', 'washrooms', 'restroom', 'restrooms', 'bathroom', 'bathrooms', 'lavatory', 'wc', 'loo', 'টয়লেট', 'বাথরুম', 'ওয়াশরুম', 'শৌচাগার'],
        'prayer_room' => ['prayer room', 'prayer', 'namaz', 'salah', 'salat', 'mosque', 'নামাজ', 'নামাজের', 'মসজিদ'],
        'information' => ['information desk', 'info desk', 'help desk', 'helpdesk', 'reception', 'তথ্য কেন্দ্র', 'হেল্প ডেস্ক'],
        'exit' => ['exit', 'exits', 'way out', 'প্রস্থান', 'বাহির'],
        'atm' => ['atm', 'atms', 'cash machine', 'cashpoint', 'এটিএম'],
        'lift' => ['lift', 'lifts', 'elevator', 'elevators', 'লিফট'],
    ];

    private const GENERIC_WORDS = ['facility', 'facilities', 'amenity', 'amenities', 'সুবিধা'];

    private const STEP_FREE_WORDS = ['wheelchair', 'step free', 'stepfree', 'accessible', 'disabled', 'হুইলচেয়ার', 'প্রতিবন্ধী'];

    public function __construct(
        private FacilityService $facilities,
        private DirectoryLookup $directory,
        private NavigationHandoff $handoff,
        private TextAnalyzer $analyzer,
    ) {}

    /**
     * The facility type a message names, if any.
     */
    public function typeIn(string $message): ?FacilityType
    {
        $text = $this->analyzer->normalise($message);

        foreach (self::TYPE_WORDS as $type => $words) {
            if ($this->containsAny($text, $words)) {
                return FacilityType::from($type);
            }
        }

        return null;
    }

    public function mentionsFacilities(string $message): bool
    {
        return $this->typeIn($message) !== null || $this->containsAny($this->analyzer->normalise($message), self::GENERIC_WORDS);
    }

    public function run(Mall $mall, string $message, string $language): ToolResultData
    {
        $text = $this->analyzer->normalise($message);
        $type = $this->typeIn($message);
        $filters = [
            'type' => $type?->value,
            'accessible_only' => $this->containsAny(str_replace('-', ' ', $text), self::STEP_FREE_WORDS),
            'floor' => $this->floorIn($mall, $text),
        ];

        $found = $this->facilities->find($mall, $filters);

        if ($found->isEmpty() && $filters['floor'] !== null) {
            $found = $this->facilities->find($mall, [...$filters, 'floor' => null]);
        }

        if ($found->isEmpty()) {
            return ToolResultData::nothing();
        }

        $shown = $found->take(self::LIMIT)->values();
        $sources = [];
        $handoffs = [];

        foreach ($shown as $index => $facility) {
            $sources[] = new SourceSnippetData($index + 1, SourceSnippetData::FACILITY, 'Facility directory', $this->line($facility));
            $handoffs[] = $this->handoff->forFacility($facility);
        }

        return new ToolResultData(
            outcome: AnswerOutcome::Answered,
            sources: $sources,
            shape: GenerationRequestData::LISTING,
            lead: $type === null
                ? __('Facilities of the mall (:count):', ['count' => $shown->count()], $language)
                : __(':type in the mall (:count):', ['type' => __($type->label(), [], $language), 'count' => $shown->count()], $language),
            handoffs: $handoffs,
            meta: ['filters' => array_filter($filters), 'more' => max(0, $found->count() - $shown->count())],
        );
    }

    private function line(Facility $facility): string
    {
        $line = $facility->name.' ('.$facility->facility_type->label().'): '.$facility->floor->name.' ('.$facility->floor->code.')';

        if (filled($facility->location_note)) {
            $line .= ', '.$facility->location_note;
        }

        $line .= $facility->is_accessible ? '. Step-free access.' : '. No step-free access.';

        return $facility->status === FacilityStatus::TemporarilyClosed ? $line.' Temporarily closed.' : $line;
    }

    /**
     * The code of a floor the message names by its name ("first floor") or code ("F1").
     */
    private function floorIn(Mall $mall, string $text): ?string
    {
        foreach ($this->directory->floorsOf((string) $mall->getKey()) as $floor) {
            if ($this->containsAny($text, [mb_strtolower($floor->name), mb_strtolower($floor->code).' floor', 'floor '.mb_strtolower($floor->code)])) {
                return $floor->code;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $phrases
     */
    private function containsAny(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (preg_match('/(?<![\p{L}\p{M}\p{N}])'.preg_quote($this->analyzer->normalise($phrase), '/').'(?![\p{L}\p{M}\p{N}])/u', $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
