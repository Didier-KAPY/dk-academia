<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ServiceOffering;
use App\Models\Training;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        $services = ServiceOffering::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('public.services.index', compact('services'));
    }

    public function prices(): View
    {
        $trainings = Training::query()
            ->where('is_published', true)
            ->with('category:id,name')
            ->orderBy('title')
            ->get(['id', 'category_id', 'title', 'slug', 'price', 'currency', 'duration_hours']);

        $services = ServiceOffering::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'summary', 'price_from', 'currency']);

        return view('public.prices', compact('trainings', 'services'));
    }
}
