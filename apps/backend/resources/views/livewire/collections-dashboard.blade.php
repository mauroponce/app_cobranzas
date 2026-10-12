<div class="space-y-6">
    @if (session('status')) <div class="alert alert-success"><span>{{ session('status') }}</span></div> @endif
    @can('payments.create')
    <x-ui.card class="p-5">
        <h2 class="admin-panel-title mb-4">Registrar cobro manual</h2>
        <form wire:submit="register" class="grid gap-3 md:grid-cols-2">
            <select wire:model="customerId" class="select select-bordered w-full"><option value="">Seleccioná un cliente</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->last_name }}, {{ $customer->first_name }}</option>@endforeach</select>
            <input wire:model="amount" class="input input-bordered w-full" inputmode="decimal" placeholder="Importe en ARS, por ejemplo 1250.00">
            <select wire:model="method" class="select select-bordered w-full"><option value="cash">Efectivo</option><option value="bank_transfer">Transferencia bancaria</option></select>
            <input wire:model="reference" class="input input-bordered w-full" placeholder="Referencia de transferencia (opcional)">
            <textarea wire:model="notes" class="textarea textarea-bordered md:col-span-2" placeholder="Observaciones"></textarea>
            <button class="btn admin-action md:col-span-2" type="submit">Registrar cobro</button>
        </form>
    </x-ui.card>
    @endcan
    <x-ui.card class="overflow-x-auto p-5">
        <h2 class="admin-panel-title mb-4">Pagos</h2>
        <table class="table"><thead><tr><th>Fecha</th><th>Medio</th><th>Estado</th><th>Importe</th><th></th></tr></thead><tbody>
        @forelse($payments as $payment)<tr><td>{{ $payment->received_at?->format('d/m/Y H:i') }}</td><td>{{ $payment->method === 'cash' ? 'Efectivo' : 'Transferencia' }}</td><td>{{ $payment->status }}</td><td>${{ \App\Domain\Commercial\Decimal::display($payment->amount) }}</td><td>@if($payment->status === 'pending_verification') @can('payments.verify_transfer')<button wire:click="verify('{{ $payment->id }}')" class="btn btn-sm">Verificar</button>@endcan @endif</td></tr>@empty<tr><td colspan="5">No hay pagos registrados.</td></tr>@endforelse
        </tbody></table>{{ $payments->links() }}
    </x-ui.card>
</div>
