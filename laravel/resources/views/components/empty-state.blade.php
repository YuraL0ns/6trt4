@props([
    'icon' => null,
    'title' => 'Нет данных',
    'description' => 'Здесь пока ничего нет',
])

<div class="text-center py-12">
    @if($icon)
        <div class="mx-auto w-16 h-16 text-gray-600 mb-4">
            {{ $icon }}
        </div>
    @else
        <svg class="mx-auto w-16 h-16 text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
        </svg>
    @endif
    
    <h3 class="text-lg font-medium text-gray-300 mb-2">{{ $title }}</h3>
    <p class="text-sm text-gray-500">{{ $description }}</p>
    
    @isset($action)
        <div class="mt-6">
            {{ $action }}
        </div>
    @endisset
</div>


