@props([
    'headers' => [],
])

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-800">
        @if(!empty($headers))
            <thead class="bg-[#1e1e1e]">
                <tr>
                    @foreach($headers as $header)
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="bg-[#121212] divide-y divide-gray-800">
            {{ $slot }}
        </tbody>
    </table>
</div>


