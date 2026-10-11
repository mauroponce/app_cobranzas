@props(['label', 'href', 'active' => false, 'icon' => 'dashboard'])
<a href="{{ $href }}" @class(['admin-nav-link', 'is-active' => $active]) @if($active) aria-current="page" @endif><x-ui.icon :name="$icon" /><span>{{ $label }}</span></a>
