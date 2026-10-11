<x-layouts.app title="Detalle de usuario">
    <a class="link link-primary" href="{{ route('admin.users') }}">← Volver a usuarios</a>
    <div class="mt-5 max-w-2xl"><x-ui.card class="p-6"><h1 class="admin-title">{{ $membership->user->name }}</h1><p class="admin-muted mt-2">{{ $membership->user->email }}</p><dl class="mt-5 grid grid-cols-2 gap-4"><div><dt class="admin-muted">Rol</dt><dd>{{ \App\Domain\Tenancy\RoleMatrix::label($membership->role) }}</dd></div><div><dt class="admin-muted">Estado</dt><dd>{{ $membership->status === 'active' ? 'Activo' : ($membership->status === 'suspended' ? 'Suspendido' : 'Invitado') }}</dd></div><div><dt class="admin-muted">Incorporación</dt><dd>{{ $membership->joined_at?->format('d/m/Y') ?? '—' }}</dd></div></dl>
    @if(session('status'))<div class="alert alert-success mt-5">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-error mt-5">{{ $errors->first() }}</div>@endif
    @can('users.assign_roles')
        @if(\App\Domain\Tenancy\RoleMatrix::canManage(request()->attributes->get('activeMembership')->role, $membership->role) && $membership->role !== 'owner')
        <form method="POST" action="{{ route('admin.users.role', $membership) }}" class="mt-6 flex flex-wrap items-end gap-3">@csrf @method('PATCH')<label class="form-control flex-1"><span class="label">Cambiar rol</span><select name="role" class="select select-bordered w-full">@foreach(\App\Domain\Tenancy\RoleMatrix::ROLES as $role)@if(\App\Domain\Tenancy\RoleMatrix::canAssign(request()->attributes->get('activeMembership')->role, $role))<option value="{{ $role }}" @selected($membership->role === $role)>{{ \App\Domain\Tenancy\RoleMatrix::label($role) }}</option>@endif @endforeach</select></label><button class="btn admin-action">Guardar rol</button></form>
        @endif
    @endcan
    @can('users.disable')
        @if(\App\Domain\Tenancy\RoleMatrix::canManage(request()->attributes->get('activeMembership')->role, $membership->role))
        <form method="POST" action="{{ route('admin.users.status', $membership) }}" class="mt-5" onsubmit="return confirm('¿Confirmás el cambio de acceso?')">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $membership->status === 'active' ? 'suspended' : 'active' }}"><button class="btn btn-outline">{{ $membership->status === 'active' ? 'Suspender acceso' : 'Reactivar acceso' }}</button></form>
        @endif
    @endcan
    @if(request()->attributes->get('activeMembership')->role === 'owner' && $membership->status === 'active' && $membership->user_id !== auth()->id())
        <form method="POST" action="{{ route('admin.users.transfer', $membership) }}" class="mt-5" onsubmit="return confirm('¿Transferir la propiedad de esta empresa? Tu rol pasará a administrador.')">@csrf<button class="btn btn-outline">Transferir propiedad</button></form>
    @endif
    </x-ui.card></div>
</x-layouts.app>
