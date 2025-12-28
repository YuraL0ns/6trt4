@props([
    'type' => 'info', // success, error, warning, info
    'dismissible' => false,
])

@php
    $typeClasses = [
        'success' => 'bg-green-900/50 border-green-700 text-green-200',
        'error' => 'bg-red-900/50 border-red-700 text-red-200',
        'warning' => 'bg-yellow-900/50 border-yellow-700 text-yellow-200',
        'info' => 'bg-blue-900/50 border-blue-700 text-blue-200',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'border rounded-lg p-4 ' . $typeClasses[$type]]) }}>
    <div class="flex items-start">
        <div class="flex-1">
            {{ $slot }}
        </div>
        @if($dismissible)
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-current opacity-70 hover:opacity-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        @endif
    </div>
</div>


