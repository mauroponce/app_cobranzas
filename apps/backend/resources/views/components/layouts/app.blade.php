<!doctype html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} · App Cobranzas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<input id="admin-sidebar-toggle" class="admin-sidebar-toggle" type="checkbox" aria-label="Mostrar navegación">
<div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Navegación principal">
        <div class="admin-brand">
            <span class="admin-brand-icon"><x-ui.icon name="layers" class="h-5 w-5" /></span>
            <span><strong>app cobranzas<span class="text-indigo-600">.</span></strong><small>PLATAFORMA SaaS</small></span>
        </div>
        <a class="admin-company" href="{{ route('tenants.choose') }}" aria-label="Cambiar empresa">
            <span class="admin-company-initial">{{ strtoupper(substr(request()->attributes->get('activeTenant')?->name ?? 'E', 0, 1)) }}</span>
            <span class="min-w-0 flex-1"><strong>{{ request()->attributes->get('activeTenant')?->name ?? 'Elegir empresa' }}</strong><small>Empresa activa</small></span>
            <x-ui.icon name="chevrons" class="text-slate-400" />
        </a>
        <nav class="admin-sidebar-scroll">
            @if(request()->attributes->get('activeTenant'))<p class="admin-nav-caption">OPERACIONES</p>
            <x-ui.nav-link icon="dashboard" label="Dashboard" href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')" />
            @can('customers.view')<x-ui.nav-link icon="users" label="Clientes" :href="route('customers.index')" :active="request()->routeIs('customers.*')" />@endcan
            @can('products.view')<x-ui.nav-link icon="bag" label="Productos" :href="route('products.index')" :active="request()->routeIs('products.*')" />@endcan
            @can('quotations.view')<x-ui.nav-link icon="receipt" label="Presupuestos" :href="route('quotations.index')" :active="request()->routeIs('quotations.*')" />@endcan
            @foreach (['sales' => ['Ventas','bag'], 'collections' => ['Cobranzas','wallet'], 'routes' => ['Rutas y visitas','route'], 'collectors' => ['Cobradores','user-cog'], 'settlements' => ['Rendiciones','receipt']] as $section => [$label, $icon])
                @can(match($section) {'sales' => 'sales.view', 'collections' => 'collections.view', 'routes' => 'routes.view', 'collectors' => 'routes.view', 'settlements' => 'settlements.view'})<x-ui.nav-link :icon="$icon" :label="$label" :href="route('modules.show', $section)" :active="request()->route('section') === $section" />@endcan
            @endforeach
            <p class="admin-nav-caption">ADMINISTRACIÓN</p>
            @can('users.view')<x-ui.nav-link icon="user-cog" label="Usuarios y roles" href="{{ route('admin.users') }}" :active="request()->routeIs('admin.users*')" />@endcan
            @can('users.view')<x-ui.nav-link icon="mail" label="Invitaciones" href="{{ route('admin.invitations') }}" :active="request()->routeIs('admin.invitations*')" />@endcan
            @can('reports.view')<x-ui.nav-link icon="chart" label="Reportes" :href="route('modules.show', 'reports')" :active="request()->route('section') === 'reports'" />@endcan
            @can('tenant.view')<x-ui.nav-link icon="settings" label="Configuración" :href="route('admin.company')" :active="request()->routeIs('admin.company')" />@endcan
            @endif
            @can('platform.access')<p class="admin-nav-caption">PLATAFORMA</p><x-ui.nav-link icon="layers" label="Empresas" :href="route('platform.tenants')" :active="request()->routeIs('platform.*')" />@endcan
        </nav>
        <div class="admin-sidebar-footer">
            <span class="admin-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
            <span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ auth()->user()->name }}</strong><small>{{ (request()->attributes->get('activeMembership') ? \App\Domain\Tenancy\RoleMatrix::label(request()->attributes->get('activeMembership')->role) : null) ?? (auth()->user()->platformAdministrator()->exists() ? 'Operador de plataforma' : 'Usuario') }}</small></span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="admin-icon-button" aria-label="Cerrar sesión" title="Cerrar sesión"><x-ui.icon name="logout" /></button></form>
        </div>
    </aside>
    <label for="admin-sidebar-toggle" class="admin-sidebar-overlay" aria-label="Cerrar navegación"></label>
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="flex items-center gap-3"><label for="admin-sidebar-toggle" class="admin-mobile-menu" aria-label="Abrir navegación"><x-ui.icon name="menu" /></label><span class="admin-breadcrumb">{{ request()->attributes->get('activeTenant')?->name ?? 'Empresas' }} <span class="mx-2">/</span> <strong>{{ $title ?? 'Dashboard' }}</strong></span></div>
            <div class="flex items-center gap-3"><button type="button" class="admin-icon-button" data-theme-toggle aria-label="Cambiar modo de color" title="Cambiar modo de color"><x-ui.icon name="theme" /></button><span class="admin-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span></div>
        </header>
        <main class="admin-content">{{ $slot }}</main>
    </div>
</div>
@livewireScripts
</body>
</html>
