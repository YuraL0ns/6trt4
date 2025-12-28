<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Страница не найдена - Hunter-Photo.Ru</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#121212] text-white antialiased">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="text-center max-w-2xl">
            <h1 class="text-9xl font-bold text-[#a78bfa] mb-4">404</h1>
            <h2 class="text-3xl font-bold text-white mb-4">Страница не найдена</h2>
            <p class="text-gray-400 mb-8 text-lg">
                К сожалению, запрашиваемая страница не существует или была перемещена.
            </p>
            <a href="{{ route('home') }}" class="inline-block px-6 py-3 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white font-semibold rounded-lg transition-colors">
                Вернуться на главную страницу
            </a>
        </div>
    </div>
</body>
</html>

