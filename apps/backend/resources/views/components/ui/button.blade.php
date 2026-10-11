@props(['type' => 'button', 'variant' => 'primary'])
<button type="{{ $type }}" {{ $attributes->class(['btn', 'btn-'.$variant]) }}>{{ $slot }}</button>
