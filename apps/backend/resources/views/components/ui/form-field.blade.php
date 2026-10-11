@props(['name', 'label', 'type' => 'text', 'value' => ''])
<label class="form-control w-full"><span class="label mb-1 text-sm font-medium">{{ $label }}</span><input name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" {{ $attributes->class(['input input-bordered w-full']) }} />@error($name)<span class="mt-1 text-sm text-error">{{ $message }}</span>@enderror</label>
