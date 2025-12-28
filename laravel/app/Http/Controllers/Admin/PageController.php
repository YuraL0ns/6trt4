<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Список страниц
     */
    public function index()
    {
        $pages = Page::orderBy('page_title', 'asc')->paginate(20);
        return view('admin.pages.index', compact('pages'));
    }

    /**
     * Создать страницу
     */
    public function store(Request $request)
    {
        $request->validate([
            'page_title' => 'required|string|max:255',
            'page_meta_descr' => 'nullable|string|max:500',
            'page_meta_key' => 'nullable|string|max:500',
            'page_content' => 'required|string',
            'page_url' => 'required|string|max:255|unique:pages,page_url',
        ]);

        Page::create($request->all());

        return back()->with('success', 'Страница создана');
    }

    /**
     * Обновить страницу
     */
    public function update(Request $request, string $id)
    {
        $page = Page::findOrFail($id);

        $request->validate([
            'page_title' => 'required|string|max:255',
            'page_meta_descr' => 'nullable|string|max:500',
            'page_meta_key' => 'nullable|string|max:500',
            'page_content' => 'required|string',
            'page_url' => 'required|string|max:255|unique:pages,page_url,' . $id,
        ]);

        $page->update($request->all());

        return back()->with('success', 'Страница обновлена');
    }

    /**
     * Удалить страницу
     */
    public function destroy(string $id)
    {
        $page = Page::findOrFail($id);
        $page->delete();

        return back()->with('success', 'Страница удалена');
    }
}
