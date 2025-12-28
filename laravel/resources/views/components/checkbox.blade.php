@props([
    'label' => '',
    'name' => '',
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'error' => '',
])

<div class="mb-4">
    <label class="flex items-center space-x-2 {{ $disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}">
        <input
            type="checkbox"
            name="{{ $name }}"
            value="{{ $value }}"
            {{ $checked || old($name) ? 'checked' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->merge(['class' => 'w-4 h-4 text-[#a78bfa] bg-[#121212] border-gray-700 rounded focus:ring-[#a78bfa] focus:ring-2' . ($error ? ' border-red-500' : '')]) }}
        >
        @if($label)
            <span class="text-sm text-gray-300">{{ $label }}</span>
        @endif
    </label>
    
    @if($error)
        <p class="mt-1 text-sm text-red-500">{{ $error }}</p>
    @endif
    
    @error($name)
        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
    @enderror
</div>


