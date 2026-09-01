@props([
    'name',
    'value' => '',
    'placeholder' => 'Pilih jam',
    'required' => false,
])
<select name="{{ $name }}" {{ $attributes->class(['select']) }} @required($required)>
    <option value="">{{ $placeholder }}</option>
    @for($minute = 0; $minute < 24 * 60; $minute += 30)
        @php($slot = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60))
        <option value="{{ $slot }}" @selected(substr((string) $value, 0, 5) === $slot)>{{ $slot }}</option>
    @endfor
</select>
