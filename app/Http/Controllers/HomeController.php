<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

final class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'bookCount' => DB::table('catalog_books')->count(),
            'newsCount' => DB::table('news_clippings')->count(),
            'latestNews' => DB::table('news_clippings')
                ->select('id', 'title', 'source_media', 'publish_date', 'url_link')
                ->orderByDesc('publish_date')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
