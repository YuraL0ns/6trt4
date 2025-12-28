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
                </div>
            </div>
        </div>
    @endauth
</aside>


