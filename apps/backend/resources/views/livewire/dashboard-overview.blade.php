<div>
    <div class="mb-5 grid grid-cols-2 gap-4 xl:grid-cols-4">
        @foreach ([['Cobrado este mes','wallet'],['Cuotas pendientes','calendar'],['Visitas de hoy','pin'],['Efectivo por rendir','hand']] as [$label, $icon])
            <x-ui.card class="admin-kpi">
                <div class="flex items-center justify-between gap-3"><span class="admin-muted">{{ $label }}</span><span class="admin-iconbox"><x-ui.icon :name="$icon" /></span></div>
                <p class="admin-kpi-value">—</p><span class="admin-tag mt-2">Sin datos</span>
            </x-ui.card>
        @endforeach
    </div>
    <div class="mb-5 grid gap-5 xl:grid-cols-[1.65fr_1fr]">
        <x-ui.card class="p-6"><h2 class="admin-panel-title">Evolución de cobranzas</h2><p class="admin-muted mt-1">Pagos presenciales y online</p><x-ui.empty-state icon="chart" title="Sin cobranzas registradas" description="El gráfico aparecerá cuando el módulo de cobranzas esté disponible." class="min-h-72" /></x-ui.card>
        <x-ui.card class="p-6"><h2 class="admin-panel-title">Estado de las cuotas</h2><p class="admin-muted mt-1">Distribución de obligaciones</p><x-ui.empty-state icon="calendar" title="Sin cuotas registradas" description="Este resumen estará disponible al implementar ventas y cuotas." class="min-h-72" /></x-ui.card>
    </div>
    <x-ui.card class="p-5"><div class="mb-3 flex items-center justify-between gap-3"><h2 class="admin-panel-title">Actividad reciente</h2><span class="admin-tag">Sin actividad</span></div><x-ui.empty-state icon="receipt" title="Todavía no hay actividad" description="Las operaciones reales aparecerán aquí cuando se habiliten los módulos de negocio." class="py-8" /></x-ui.card>
</div>
