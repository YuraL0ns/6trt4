@props([
    'variant' => 'default', // default, success, error, warning, info
    'size' => 'md', // sm, md, lg
])

@php
    $variantClasses = [
        'default' => 'bg-gray-700 text-gray-300',
        'success' => 'bg-green-900 text-green-200',
        'error' => 'bg-red-900 text-red-200',
        'warning' => 'bg-yellow-900 text-yellow-200',
        'info' => 'bg-blue-900 text-blue-200',
    ];
    
    $sizeClasses = [
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-sm',
        'lg' => 'px-3 py-1.5 text-base',
    ];
    
    // Безопасный доступ к вариантам - используем значение по умолчанию, если вариант не найден
    $variantClass = $variantClasses[$variant] ?? $variantClasses['default'];
    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];
    
    $classes = 'inline-flex items-center rounded-full font-medium ' . $variantClass . ' ' . $sizeClass;
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>


