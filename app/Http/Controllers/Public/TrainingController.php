<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DeliveryMode;
use App\Models\Training;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:100', 'exists:categories,slug'],
            'level' => ['nullable', 'string', 'max:50'],
            'mode' => ['nullable', 'string', 'max:100', 'exists:delivery_modes,slug'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $trainings = Training::query()
            ->where('is_published', true)
            ->with(['category:id,name,slug', 'deliveryModes:id,name,slug'])
            ->when($filters['category'] ?? null, function (Builder $query, string $slug): void {
                $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('slug', $slug));
            })
            ->when($filters['level'] ?? null, fn (Builder $query, string $level): Builder => $query->where('level', $level))
            ->when($filters['mode'] ?? null, function (Builder $query, string $slug): void {
                $query->whereHas('deliveryModes', fn (Builder $modeQuery): Builder => $modeQuery
                    ->where('slug', $slug)
                    ->where('delivery_modes.is_active', true));
            })
            ->when(isset($filters['max_price']), fn (Builder $query): Builder => $query->whereNotNull('price')->where('price', '<=', $filters['max_price']))
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('trainings', fn (Builder $query): Builder => $query->where('is_published', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $deliveryModes = DeliveryMode::query()
            ->where('is_active', true)
            ->whereHas('trainings', fn (Builder $query): Builder => $query->where('is_published', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $levels = Training::query()
            ->where('is_published', true)
            ->whereNotNull('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level');

        return view('public.trainings.index', compact('trainings', 'categories', 'deliveryModes', 'levels', 'filters'));
    }

    public function show(Training $training): View
    {
        abort_unless($training->is_published, 404);

        $training->load([
            'category',
            'deliveryModes' => fn (BelongsToMany $query): BelongsToMany => $query->where('delivery_modes.is_active', true),
            'modules.lessons' => fn (Builder $query): Builder => $query->where('is_published', true)->orderBy('sort_order'),
        ]);

        $sessions = $training->sessions()
            ->where('status', 'scheduled')
            ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('delivery_mode_id')
                ->orWhereHas('deliveryMode', fn (Builder $modeQuery): Builder => $modeQuery->where('is_active', true)))
            ->with(['deliveryMode:id,name,slug', 'instructor:id,name'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        return view('public.trainings.show', compact('training', 'sessions'));
    }
}
