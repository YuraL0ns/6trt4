@props([
    'label' => '',
    'name' => '',
    'options' => [],
    'value' => '',
    'required' => false,
    'error' => '',
])

<div class="mb-4">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-300 mb-2">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif
    
    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-[#a78bfa] focus:border-transparent transition-colors' . ($error ? ' border-red-500' : '')]) }}
    >
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" {{ old($name, $value) == $optionValue ? 'selected' : '' }}>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
    
    @if($error)
        <p class="mt-1 text-sm text-red-500">{{ $error }}</p>
    @endif
    
    @error($name)
        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
    @enderror
</div>


