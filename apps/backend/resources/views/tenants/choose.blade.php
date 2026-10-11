<x-layouts.app title="Elegir empresa">
    <div class="mb-7"><p class="admin-muted font-bold tracking-wide">ADMINISTRACIÓN</p><h1 class="admin-title mt-1">Elegí una empresa</h1><p class="admin-muted mt-2">Seleccioná la empresa con la que querés trabajar.</p></div>
    <div class="grid max-w-3xl gap-4 sm:grid-cols-2">
        @forelse($memberships as $membership)
            <form method="POST" action="{{ route('tenants.select') }}">@csrf<input type="hidden" name="tenant_id" value="{{ $membership->tenant_id }}" /><button type="submit" class="admin-panel flex w-full items-center gap-4 p-5 text-left transition hover:border-indigo-300"><span class="admin-company-initial">{{ strtoupper(substr($membership->tenant->name, 0, 1)) }}</span><span class="flex-1"><strong class="block text-sm">{{ $membership->tenant->name }}</strong><small class="admin-muted">{{ \App\Domain\Tenancy\RoleMatrix::label($membership->role) }}</small></span><x-ui.icon name="chevrons" class="text-slate-400" /></button></form>
        @empty
            <x-ui.card class="p-5 sm:col-span-2"><x-ui.empty-state icon="users" title="Sin empresas disponibles" description="Tu usuario no tiene empresas activas disponibles." /></x-ui.card>
        @endforelse
    </div>
</x-layouts.app>
