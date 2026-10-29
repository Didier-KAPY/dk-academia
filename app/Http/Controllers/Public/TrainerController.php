<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\TrainerProfile;
use Illuminate\View\View;

class TrainerController extends Controller
{
    public function index(): View
    {
        $trainers = TrainerProfile::query()
            ->where('is_public', true)
            ->with('user:id,name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(12);

        return view('public.trainers.index', compact('trainers'));
    }
}
