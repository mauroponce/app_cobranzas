<x-layouts.app title="Dashboard">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div><p class="admin-muted mb-1 font-bold tracking-wide">PANEL OPERATIVO</p><h1 class="admin-title">Buen día, {{ explode(' ', auth()->user()->name)[0] }}</h1><p class="admin-muted mt-2">Así está {{ request()->attributes->get('activeTenant')->name }} en este momento.</p></div>
        <button class="btn btn-sm admin-action" disabled title="Disponible cuando se implemente Ventas"><x-ui.icon name="plus" class="h-4 w-4" /> Nueva venta</button>
    </div>
    <livewire:dashboard-overview />
</x-layouts.app>
