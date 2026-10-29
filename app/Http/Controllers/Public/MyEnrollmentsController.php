<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyEnrollmentsController extends Controller
{
    public function index(Request $request): View
    {
        $enrollments = $request->user()
            ->enrollments()
            ->with([
                'training:id,title,slug,price,currency',
                'session:id,name,starts_at',
                'deliveryMode:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('public.enrollments.index', compact('enrollments'));
    }
}
