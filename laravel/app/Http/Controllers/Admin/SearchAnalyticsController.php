<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaceSearch;
use App\Models\Photo;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Показать аналитику поиска
     */
    public function index(Request $request)
    {
        // Статистика по поискам
        $stats = [
            'total_searches' => FaceSearch::count(),
            'unique_photos_found' => FaceSearch::distinct('photo_id')->count('photo_id'),
            'avg_similarity' => FaceSearch::avg('similarity_score') ?? 0,
            'max_similarity' => FaceSearch::max('similarity_score') ?? 0,
            'min_similarity' => FaceSearch::min('similarity_score') ?? 0,
        ];

        // Последние поиски
        $query = FaceSearch::with(['photo.event'])
            ->orderBy('created_at', 'desc');

        // Фильтр по событию
        if ($request->has('event_id') && $request->event_id) {
            $query->whereHas('photo', function($q) use ($request) {
                $q->where('event_id', $request->event_id);
            });
        }

        // Фильтр по оценке схожести
        if ($request->has('min_similarity') && $request->min_similarity) {
            $query->where('similarity_score', '>=', $request->min_similarity);
        }

        $searches = $query->paginate(50)->withQueryString();
        $events = Event::orderBy('title')->get();

        // Топ фотографий по количеству найденных совпадений
        $topPhotos = FaceSearch::select('photo_id', DB::raw('COUNT(*) as search_count'), DB::raw('AVG(similarity_score) as avg_similarity'))
            ->groupBy('photo_id')
            ->orderBy('search_count', 'desc')
            ->orderBy('avg_similarity', 'desc')
            ->with('photo.event')
            ->limit(10)
            ->get();

        return view('admin.search-analytics.index', compact('stats', 'searches', 'events', 'topPhotos'));
    }

    /**
     * Показать детали поиска
     */
    public function show(string $id)
    {
        $search = FaceSearch::with(['photo.event'])->findOrFail($id);
        
        return view('admin.search-analytics.show', compact('search'));
    }
}

