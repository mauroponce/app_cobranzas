@props(['title' => null])
<section {{ $attributes->class(['admin-panel']) }}>@if($title)<h2 class="admin-panel-title">{{ $title }}</h2>@endif{{ $slot }}</section>
