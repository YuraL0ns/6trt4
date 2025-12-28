@props([
    'id' => 'modal',
    'title' => '',
    'size' => 'md', // sm, md, lg, xl, full
])

@php
    $sizeClasses = [
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
        'full' => 'max-w-7xl',
    ];
@endphp

<div id="{{ $id }}" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="{{ $id }}-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black bg-opacity-75 transition-opacity" onclick="document.getElementById('{{ $id }}').classList.add('hidden')"></div>
    
    <!-- Modal -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative transform overflow-hidden rounded-lg bg-[#1e1e1e] border border-gray-800 shadow-xl transition-all {{ $sizeClasses[$size] }} w-full">
            <!-- Header -->
            @if($title)
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                    <h3 class="text-lg font-semibold text-white" id="{{ $id }}-title">
                        {{ $title }}
                    </h3>
                    <button onclick="document.getElementById('{{ $id }}').classList.add('hidden')" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            @endif
            
            <!-- Content -->
            <div class="px-6 py-4">
                {{ $slot }}
            </div>
            
            <!-- Footer (if provided) -->
            @isset($footer)
                <div class="px-6 py-4 border-t border-gray-800 flex justify-end space-x-3">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>


