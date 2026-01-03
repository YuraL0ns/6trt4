<aside class="w-64 bg-[#1e1e1e] border-r border-gray-800 flex flex-col h-screen sticky top-0">
    <!-- Logo -->
    <div class="p-6 border-b border-gray-800">
        <a href="{{ route('home') }}" class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-[#a78bfa] rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-xl">H</span>
            </div>
            <span class="text-xl font-bold text-white">Hunter-Photo</span>
        </a>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
        @auth
            @if(auth()->user()->isAdmin())
                @include('layouts.navigation.admin')
                <!-- Администратор также видит меню фотографа -->
                <div class="pt-4 mt-4 border-t border-gray-800">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Интерфейс фотографа</p>
                    @include('layouts.navigation.photographer')
                </div>
            @elseif(auth()->user()->isPhotographer())
                @include('layouts.navigation.photographer')
            @else
                @include('layouts.navigation.user')
            @endif
        @else
            @include('layouts.navigation.guest')
        @endauth
        
        <!-- Страницы, созданные через админ-панель -->
        @php
            $pages = \App\Models\Page::orderBy('page_title', 'asc')->get();
        @endphp
        @if($pages->count() > 0)
            <div class="pt-4 mt-4 border-t border-gray-800">
                <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Информация</p>
                @foreach($pages as $page)
                    @php
                        $pageUrl = ltrim($page->page_url, '/');
                        $isActive = request()->is('pages/' . $pageUrl);
                    @endphp
                    <a href="{{ route('pages.show', $pageUrl) }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors {{ $isActive ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>{{ $page->page_title }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </nav>
    
    <!-- User Info -->
    @auth
        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-[#a78bfa] rounded-full flex items-center justify-center">
                    <span class="text-white text-sm font-semibold">
                        {{ strtoupper(substr(auth()->user()->last_name, 0, 1) . substr(auth()->user()->first_name, 0, 1)) }}
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">
                        {{ auth()->user()->full_name }}
                    </p>
                    <p class="text-xs text-gray-400 truncate">
                        {{ auth()->user()->email }}
                    </p>
                    @if(auth()->user()->isPhotographer())
                        @php
                            $user = auth()->user()->fresh();
                            $balance = $user->balance ?? 0;
                        @endphp
                        <p class="text-xs text-[#a78bfa] font-semibold mt-1">
                            Баланс: {{ number_format($balance, 2, ',', ' ') }} ₽
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endauth
</aside>


