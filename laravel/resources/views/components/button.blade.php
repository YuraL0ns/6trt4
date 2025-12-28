@props([
    'variant' => 'primary', // primary, secondary, danger, warning, outline
    'size' => 'md', // sm, md, lg
    'type' => 'button',
    'href' => null,
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[#121212]';
    
    $variantClasses = [
        'primary' => 'bg-[#a78bfa] text-white hover:bg-[#8b5cf6] focus:ring-[#a78bfa]',
        'secondary' => 'bg-gray-700 text-white hover:bg-gray-600 focus:ring-gray-500',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'warning' => 'bg-yellow-600 text-white hover:bg-yellow-700 focus:ring-yellow-500',
        'outline' => 'border-2 border-[#a78bfa] text-[#a78bfa] hover:bg-[#a78bfa] hover:text-white focus:ring-[#a78bfa]',
    ];
    
    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-base',
        'lg' => 'px-6 py-3 text-lg',
    ];
    
    // Безопасный доступ к вариантам - используем значение по умолчанию, если вариант не найден
    $variantClass = $variantClasses[$variant] ?? $variantClasses['primary'];
    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];
    
    $classes = $baseClasses . ' ' . $variantClass . ' ' . $sizeClass;
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif


