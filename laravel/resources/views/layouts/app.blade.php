<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Hunter-Photo.Ru')</title>
    
    @stack('styles')
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x/dist/cdn.min.js"></script>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#121212] text-white antialiased">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        @include('layouts.sidebar')
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            @include('layouts.header')
            
            <!-- Page Content -->
            <main class="flex-1 p-6">
                @yield('content')
            </main>
        </div>
    </div>
    
    @stack('scripts')
</body>
</html>

