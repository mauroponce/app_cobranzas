<x-layouts.app title="Usuarios y roles">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3"><div><p class="admin-muted font-bold tracking-wide">ADMINISTRACIÓN</p><h1 class="admin-title mt-1">Usuarios y roles</h1><p class="admin-muted mt-2">Integrantes de {{ request()->attributes->get('activeTenant')->name }}.</p></div>@can('users.create')<a href="{{ route('admin.invitations') }}" class="btn btn-sm admin-action"><x-ui.icon name="plus" class="h-4 w-4" /> Invitar usuario</a>@endcan</div>
    @if(session('status'))<div class="alert alert-success mb-4">{{ session('status') }}</div>@endif
    <form method="GET" class="mb-5 grid gap-3 md:grid-cols-[1fr_12rem_12rem_auto]" aria-label="Filtrar usuarios">
        <input class="input input-bordered w-full" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nombre o correo">
        <select class="select select-bordered w-full" name="role"><option value="">Todos los roles</option>@foreach(\App\Domain\Tenancy\RoleMatrix::ROLES as $role)<option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ \App\Domain\Tenancy\RoleMatrix::label($role) }}</option>@endforeach</select>
        <select class="select select-bordered w-full" name="status"><option value="">Todos los estados</option>@foreach(['active' => 'Activo', 'invited' => 'Invitado', 'suspended' => 'Suspendido'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        <button class="btn btn-outline">Filtrar</button>
    </form>
    <x-ui.table><thead><tr><th>Usuario</th><th>Correo electrónico</th><th>Rol</th><th>Estado</th><th>Incorporación</th><th>Acción</th></tr></thead><tbody>
    @forelse($memberships as $membership)
        <tr><td><div class="flex items-center gap-3"><span class="admin-avatar">{{ strtoupper(substr($membership->user->name, 0, 2)) }}</span><strong>{{ $membership->user->name }}</strong></div></td><td>{{ $membership->user->email }}</td><td>{{ \App\Domain\Tenancy\RoleMatrix::label($membership->role) }}</td><td><span class="admin-tag {{ $membership->status === 'active' ? '!bg-emerald-50 !text-emerald-700' : '!bg-amber-50 !text-amber-700' }}">{{ $membership->status === 'active' ? 'Activo' : ($membership->status === 'suspended' ? 'Suspendido' : 'Invitado') }}</span></td><td>{{ $membership->joined_at?->format('d/m/Y') ?? '—' }}</td><td><a class="link link-primary" href="{{ route('admin.users.show', $membership) }}">Ver detalle</a></td></tr>
    @empty<tr><td colspan="6" class="py-12 text-center text-slate-500">No se encontraron usuarios.</td></tr>@endforelse
    </tbody></x-ui.table>
    <div class="mt-5">{{ $memberships->links() }}</div>
</x-layouts.app>
