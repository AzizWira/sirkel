@props(['iconOnly' => false, 'size' => null])
@if($iconOnly)
    <img {{ $attributes->merge(['class' => 'sirkel-brand-icon']) }} src="{{ asset('brand/sirkel-icon.png') }}" alt="SIRKEL"
        @if($size) width="{{ $size }}" height="{{ $size }}" @endif>
@else
    <span {{ $attributes->merge(['class' => 'sirkel-brand-wordmark']) }} aria-label="SIRKEL">
        <img class="sirkel-wordmark sirkel-wordmark-light" src="{{ asset('brand/sirkel-wordmark-light.png') }}" alt=""
            aria-hidden="true">
        <img class="sirkel-wordmark sirkel-wordmark-dark" src="{{ asset('brand/sirkel-wordmark-dark.png') }}" alt=""
            aria-hidden="true">
    </span>
@endif