<div class="space-y-6">
    @if (session('status')) <div class="alert alert-success"><span>{{ session('status') }}</span></div> @endif
    <x-ui.card class="overflow-x-auto p-5">
        <h2 class="admin-panel-title mb-4">Declaraciones de cobro</h2>
        <table class="table"><thead><tr><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Importe</th><th></th></tr></thead><tbody>
        @forelse($declarations as $declaration)
            <tr><td>{{ $declaration->declared_at?->format('d/m/Y H:i') }}</td><td>{{ $declaration->customer_id }}</td><td>{{ $declaration->status }}</td><td>$ {{ \App\Domain\Commercial\Decimal::display($declaration->amount) }}</td><td>@if($declaration->status === 'pending') @can('payments.verify')<button wire:click="approve('{{ $declaration->id }}')" class="btn btn-sm">Conciliar</button>@endcan @endif</td></tr>
        @empty<tr><td colspan="5">No hay declaraciones pendientes.</td></tr>@endforelse
        </tbody></table>{{ $declarations->links() }}
    </x-ui.card>
</div>
