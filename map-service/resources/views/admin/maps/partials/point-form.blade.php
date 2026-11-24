{{-- One form for adding a point ($point null) and for editing one; the fields shown follow the kind. --}}
@php
    $isNew = $point === null;
    $value = fn (string $field, mixed $current, mixed $default = null) => $isNew ? old($field, $default) : $current;
    $qrYaw = $isNew ? old('qr_yaw', 0) : (int) round($point['qr']['yaw_deg'] ?? 0);
@endphp
<form method="post" action="{{ $isNew ? route('admin.maps.end-points.store', $map) : $point['update_url'] }}" class="grid" data-point-form data-new="{{ $isNew ? '1' : '0' }}">
    @csrf
    @unless ($isNew)
        @method('patch')
    @endunless
    <label>What is it
        <select name="kind" required>
            @foreach ($kinds as $kindValue => $kindLabel)
                <option value="{{ $kindValue }}" @selected($value('kind', $point['kind'] ?? null, 'node') === $kindValue)>{{ $kindLabel }}</option>
            @endforeach
        </select>
    </label>
    <label>Name (for a QR code: where it hangs) <input type="text" name="name" value="{{ $value('name', $point['name'] ?? null) }}" maxlength="120" required placeholder="e.g. Kiosk by the south entrance"></label>
    <label>Floor
        <select name="floor_code" required>
            @foreach ($floors as $floor)
                <option value="{{ $floor->floor_code }}" @selected($value('floor_code', $point['floor_code'] ?? null) === $floor->floor_code)>{{ $floor->floor_code }} · {{ $floor->name }}</option>
            @endforeach
        </select>
    </label>
    <label>x (metres) <input type="number" name="x" step="0.01" value="{{ $value('x', $point['position']['x'] ?? null) }}" required></label>
    <label>y (metres) <input type="number" name="y" step="0.01" value="{{ $value('y', $point['position']['y'] ?? null) }}" required></label>
    <label>Height above the floor (m) <input type="number" name="height" step="0.05" min="0" max="10" value="{{ $value('height', $point['height_m'] ?? null, \App\Models\MapEndPoint::DEFAULT_HEIGHT_M) }}" required></label>
    <label data-kind-field="shop_entrance">Shop unit code <input type="text" name="unit_code" value="{{ $value('unit_code', $point['unit_code'] ?? null) }}" maxlength="40" placeholder="G-05"></label>
    <label class="check" data-kind-field="shop_entrance"><input type="hidden" name="is_primary" value="0"><input type="checkbox" name="is_primary" value="1" @checked($value('is_primary', $point['is_primary'] ?? false))> Main door</label>
    <label data-kind-field="facility">Facility type
        <select name="facility_type">
            @foreach ($facilityTypes as $typeValue => $typeLabel)
                <option value="{{ $typeValue }}" @selected($value('facility_type', $point['facility_type'] ?? null, 'toilet') === $typeValue)>{{ $typeLabel }}</option>
            @endforeach
        </select>
    </label>
    <label data-kind-field="checkpoint">QR code <input type="text" name="checkpoint_code" value="{{ $value('checkpoint_code', $point['checkpoint_code'] ?? null) }}" maxlength="60" placeholder="G-C10"></label>
    <label data-kind-field="checkpoint">Facing (degrees: 0 east, 90 north, 180 west, 270 south) <input type="number" name="qr_yaw" step="1" min="0" max="359" value="{{ $qrYaw }}"></label>
    <div><button class="primary" type="submit">{{ $isNew ? 'Add point' : 'Save point' }}</button></div>
</form>
