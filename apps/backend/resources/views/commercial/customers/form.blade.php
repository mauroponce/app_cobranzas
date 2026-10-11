<x-layouts.app :title="$customer ? 'Editar cliente' : 'Nuevo cliente'">
    <div class="mb-6"><a class="link link-primary text-sm" href="{{ $customer ? route('customers.show', $customer) : route('customers.index') }}">← Clientes</a><h1 class="admin-title mt-2">{{ $customer ? 'Editar cliente' : 'Nuevo cliente' }}</h1></div>
    @if($errors->any())<div class="alert alert-error mb-4"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $customer ? route('customers.update', $customer) : route('customers.store') }}" class="admin-panel max-w-4xl p-6">@csrf @if($customer) @method('PATCH') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <label class="commercial-form-control"><span class="label-text mb-1">Nombre *</span><input class="input input-bordered" name="first_name" required maxlength="255" value="{{ old('first_name', $customer?->first_name) }}"></label>
            <label class="commercial-form-control"><span class="label-text mb-1">Apellido *</span><input class="input input-bordered" name="last_name" required maxlength="255" value="{{ old('last_name', $customer?->last_name) }}"></label>
            <label class="commercial-form-control"><span class="label-text mb-1">Tipo de documento</span><select class="select select-bordered" name="document_type"><option value="">Sin documento</option>@foreach(['DNI','CUIT','CUIL','OTHER'] as $type)<option value="{{ $type }}" @selected(old('document_type', $customer?->document_type) === $type)>{{ $type === 'OTHER' ? 'Otro' : $type }}</option>@endforeach</select></label>
            <label class="commercial-form-control"><span class="label-text mb-1">Número de documento</span><input class="input input-bordered" name="document_number" maxlength="32" value="{{ old('document_number', $customer?->document_number) }}"></label>
            <label class="commercial-form-control"><span class="label-text mb-1">Correo electrónico</span><input class="input input-bordered" type="email" name="email" value="{{ old('email', $customer?->email) }}"></label>
            <label class="commercial-form-control"><span class="label-text mb-1">Teléfono</span><input class="input input-bordered" name="phone" value="{{ old('phone', $customer?->phone) }}"></label>
            <label class="commercial-form-control"><span class="label-text mb-1">WhatsApp</span><input class="input input-bordered" name="whatsapp_phone" value="{{ old('whatsapp_phone', $customer?->whatsapp_phone) }}"></label>
            <label class="commercial-form-control"><span class="label-text mb-1">Fecha de nacimiento</span><input class="input input-bordered" type="date" name="birth_date" value="{{ old('birth_date', $customer?->birth_date?->format('Y-m-d')) }}"></label>
            <label class="commercial-form-control md:col-span-2"><span class="label-text mb-1">Observaciones</span><textarea class="textarea textarea-bordered" name="notes" rows="3">{{ old('notes', $customer?->notes) }}</textarea></label>
        </div><div class="mt-6 flex justify-end gap-2"><a class="btn btn-ghost" href="{{ $customer ? route('customers.show', $customer) : route('customers.index') }}">Cancelar</a><button class="btn admin-action">Guardar cliente</button></div>
    </form>
</x-layouts.app>
