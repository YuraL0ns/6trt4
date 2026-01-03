<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Отобразить страницу по URL
     */
    public function show($url)
    {
        // Убираем начальный слэш, если есть
        $url = ltrim($url, '/');
        
        // Нормализуем URL - добавляем слэш в начале, если его нет
        $normalizedUrl = '/' . $url;
        
        // Ищем страницу по URL (с слэшем и без)
        $page = Page::where(function($query) use ($normalizedUrl, $url) {
                $query->where('page_url', $normalizedUrl)
                      ->orWhere('page_url', $url);
            })
            ->first();
        
        if (!$page) {
            abort(404, 'Страница не найдена');
        }
        
        return view('pages.show', compact('page'));
    }
}

