<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SitePage;
use Illuminate\View\View;

class SitePageController extends Controller
{
    public function about(): View
    {
        return $this->showPage('about');
    }

    public function mission(): View
    {
        return $this->showPage('mission');
    }

    private function showPage(string $slug): View
    {
        $page = SitePage::forSlug($slug);
        $paragraphs = preg_split('/\R{2,}/u', trim((string) $page->body), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return view('public.site-page', compact('page', 'paragraphs'));
    }
}
