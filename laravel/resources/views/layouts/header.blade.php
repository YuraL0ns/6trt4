@php
    use Illuminate\Support\Facades\Storage;
@endphp

<header class="bg-[#1e1e1e] border-b border-gray-800 px-6 py-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">@yield('page-title', 'Главная')</h1>
            @hasSection('page-description')
                <p class="text-sm text-gray-400 mt-1">@yield('page-description')</p>
            @endif
        </div>
        
        <div class="flex items-center space-x-4 flex-1 max-w-2xl ml-8">
            <!-- Поиск событий -->
            <form action="{{ route('events.index') }}" method="GET" class="flex-1">
                <div class="relative">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}"
                        placeholder="Поиск по названию или городу события..." 
                        class="w-full px-4 py-2 pl-10 bg-[#1a1a1a] border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#a78bfa] focus:border-transparent"
                    >
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </form>
        </div>
        
        <div class="flex items-center space-x-4">
            @auth
                <!-- Notifications -->
                <a href="{{ route('notifications.index') }}" class="relative p-2 text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    @php
                        try {
                            $unreadCount = \App\Models\Notification::where('user_id', auth()->id())->whereNull('read_at')->count();
                        } catch (\Exception $e) {
                            $unreadCount = 0;
                        }
                    @endphp
                    @if($unreadCount > 0)
                        <span class="absolute top-0 right-0 block h-5 w-5 rounded-full bg-[#a78bfa] text-white text-xs font-semibold flex items-center justify-center ring-2 ring-[#1e1e1e]">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                    @endif
                </a>
                
                <!-- User Menu -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-800 transition-colors">
                        @if(auth()->user()->avatar)
                            @php
                                $avatarPath = auth()->user()->avatar;
                                $fullPath = storage_path('app/public/' . $avatarPath);
                                $avatarUrl = file_exists($fullPath) 
                                    ? Storage::url($avatarPath) 
                                    : (file_exists($fullPath) ? asset('storage/' . $avatarPath) : null);
                            @endphp
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="Аватар" class="w-8 h-8 rounded-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            @else
                                <div class="w-8 h-8 bg-[#a78bfa] rounded-full flex items-center justify-center cursor-pointer transition-transform hover:scale-110">
                                    <span class="text-white text-xs font-semibold">
                                        {{ strtoupper(substr(auth()->user()->last_name, 0, 1) . substr(auth()->user()->first_name, 0, 1)) }}
                                    </span>
                                </div>
                            @endif
                            <div class="w-8 h-8 bg-[#a78bfa] rounded-full flex items-center justify-center cursor-pointer transition-transform hover:scale-110" style="display: none;">
                                <span class="text-white text-xs font-semibold">
                                    {{ strtoupper(substr(auth()->user()->last_name, 0, 1) . substr(auth()->user()->first_name, 0, 1)) }}
                                </span>
                            </div>
                        @else
                            <div class="w-8 h-8 bg-[#a78bfa] rounded-full flex items-center justify-center cursor-pointer transition-transform hover:scale-110">
                                <span class="text-white text-xs font-semibold">
                                    {{ strtoupper(substr(auth()->user()->last_name, 0, 1) . substr(auth()->user()->first_name, 0, 1)) }}
                                </span>
                            </div>
                        @endif
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-48 bg-[#1e1e1e] border border-gray-800 rounded-lg shadow-lg z-50">
                        <div class="py-1">
                            <a href="{{ route('profile.index') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">Профиль</a>
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">Настройки</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">Выйти</button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="px-4 py-2 text-sm font-medium text-white bg-[#a78bfa] rounded-lg hover:bg-[#8b5cf6] transition-colors">
                    Войти
                </a>
            @endauth
        </div>
    </div>
</header>


