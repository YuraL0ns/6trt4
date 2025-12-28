<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Показать настройки
     */
    public function index()
    {
        $allSettings = Setting::orderBy('key', 'asc')->get();
        $settings = [];
        foreach ($allSettings as $setting) {
            $settings[$setting->key] = $setting;
        }
        return view('admin.settings.index', compact('settings'));
    }

    /**
     * Обновить настройки
     */
    public function update(Request $request)
    {
        $request->validate([
            'site_title' => 'nullable|string|max:255',
            'site_description' => 'nullable|string|max:500',
            'percent_for_sales' => 'nullable|numeric|min:0|max:100',
            'percent_for_salary' => 'nullable|numeric|min:0|max:100',
            'site_logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'site_favicon' => 'nullable|image|mimes:ico,png|max:512',
        ]);

        foreach ($request->except(['_token', 'site_logo', 'site_favicon']) as $key => $value) {
            if ($value !== null) {
                Setting::set($key, $value);
            }
        }

        // Обработка загрузки файлов
        if ($request->hasFile('site_logo')) {
            $logoPath = $request->file('site_logo')->store('settings', 'public');
            Setting::set('site_logo', $logoPath);
        }

        if ($request->hasFile('site_favicon')) {
            $faviconPath = $request->file('site_favicon')->store('settings', 'public');
            Setting::set('site_favicon', $faviconPath);
        }

        return back()->with('success', 'Настройки обновлены');
    }
}
