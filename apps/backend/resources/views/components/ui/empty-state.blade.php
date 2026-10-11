@props(['icon' => 'chart', 'title', 'description'])
<div {{ $attributes->class(['flex flex-col items-center justify-center gap-3 px-6 py-12 text-center']) }}>
    <span class="admin-iconbox"><x-ui.icon :name="$icon" /></span>
    <strong class="text-sm font-bold">{{ $title }}</strong>
    <p class="admin-muted max-w-sm">{{ $description }}</p>
</div>
