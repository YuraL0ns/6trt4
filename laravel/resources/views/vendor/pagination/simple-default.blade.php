@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Пагинация">
        <div class="flex justify-between">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-[#1e1e1e] border border-gray-700 cursor-default leading-5 rounded-lg">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#1e1e1e] border border-gray-700 leading-5 rounded-lg hover:bg-[#121212] focus:outline-none focus:ring ring-gray-300 focus:border-[#a78bfa] active:bg-gray-900 transition ease-in-out duration-150">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-white bg-[#1e1e1e] border border-gray-700 leading-5 rounded-lg hover:bg-[#121212] focus:outline-none focus:ring ring-gray-300 focus:border-[#a78bfa] active:bg-gray-900 transition ease-in-out duration-150">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-400 bg-[#1e1e1e] border border-gray-700 cursor-default leading-5 rounded-lg">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>
    </nav>
@endif

