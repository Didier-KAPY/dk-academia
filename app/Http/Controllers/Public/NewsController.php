<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(): View
    {
        $posts = NewsPost::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9);

        return view('public.news.index', compact('posts'));
    }

    public function show(NewsPost $newsPost): View
    {
        abort_unless(
            $newsPost->is_published && $newsPost->published_at !== null && $newsPost->published_at->lessThanOrEqualTo(now()),
            404,
        );

        return view('public.news.show', ['post' => $newsPost]);
    }
}
