@props([
    'title' => '',
    'subtitle' => '',
])

<div {{ $attributes->merge(['class' => 'bg-[#1e1e1e] border border-gray-800 rounded-lg shadow-lg']) }}>
    @if($title || $subtitle)
        <div class="px-6 py-4 border-b border-gray-800">
            @if($title)
                <h3 class="text-lg font-semibold text-white">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="text-sm text-gray-400 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
    @endif
    
    <div class="p-6">
        {{ $slot }}
    </div>
</div>


