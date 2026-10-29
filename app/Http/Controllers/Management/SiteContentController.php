<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreNewsPostRequest;
use App\Http\Requests\Management\UpdateNewsPostRequest;
use App\Http\Requests\Management\UpdateSitePageRequest;
use App\Models\ActivityLog;
use App\Models\NewsPost;
use App\Models\SitePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SiteContentController extends Controller
{
    public function pages(): View
    {
        return view('management.site-content.pages.index', [
            'pages' => collect(['about', 'mission'])->map(fn (string $slug): SitePage => SitePage::forSlug($slug)),
        ]);
    }

    public function editPage(string $pageSlug): View
    {
        $page = SitePage::forSlug($pageSlug);

        return view('management.site-content.pages.edit', compact('page'));
    }

    public function updatePage(UpdateSitePageRequest $request, string $pageSlug): RedirectResponse
    {
        SitePage::forSlug($pageSlug);
        $page = SitePage::query()->firstOrNew(['slug' => $pageSlug]);
        $page->fill($request->validated());
        $page->updated_by = $request->user()->id;
        $page->save();

        $this->recordActivity($request, 'site_page.updated', $page, 'Le contenu de la page « '.$page->title.' » a été modifié.');

        return redirect()->route('management.content.pages.index')->with('status', 'La page a été mise à jour.');
    }

    public function newsIndex(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));
        $posts = NewsPost::query()
            ->when($status === 'published', fn ($query) => $query->where('is_published', true))
            ->when($status === 'draft', fn ($query) => $query->where('is_published', false))
            ->when($search !== '', fn ($query) => $query->where(function ($postQuery) use ($search): void {
                $postQuery->where('title', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('management.site-content.news.index', compact('posts', 'search', 'status'));
    }

    public function createNews(): View
    {
        return view('management.site-content.news.form', [
            'post' => new NewsPost(['is_published' => false]),
        ]);
    }

    public function storeNews(StoreNewsPostRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['slug'] = $this->uniqueSlug((string) ($data['slug'] ?? ''), (string) $data['title']);
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published'] ? ($data['published_at'] ?? now()) : null;
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeNewsImage($request);
        }

        $post = NewsPost::create($data);
        $this->recordActivity($request, 'news_post.created', $post, 'L’actualité « '.$post->title.' » a été créée.');

        return redirect()->route('management.content.news.index')->with('status', 'L’actualité a été créée.');
    }

    public function editNews(NewsPost $newsPost): View
    {
        return view('management.site-content.news.form', ['post' => $newsPost]);
    }

    public function updateNews(UpdateNewsPostRequest $request, NewsPost $newsPost): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['slug'] = $this->uniqueSlug((string) ($data['slug'] ?? ''), (string) $data['title'], $newsPost);
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published'] ? ($data['published_at'] ?? $newsPost->published_at ?? now()) : null;
        $oldImagePath = $newsPost->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeNewsImage($request);
        }

        $newsPost->update($data);

        if ($request->hasFile('image') && $this->isManagedNewsImage($oldImagePath)) {
            Storage::disk('public')->delete($oldImagePath);
        }

        $this->recordActivity($request, 'news_post.updated', $newsPost, 'L’actualité « '.$newsPost->title.' » a été modifiée.');

        return redirect()->route('management.content.news.index')->with('status', 'L’actualité a été mise à jour.');
    }

    public function toggleNewsPublication(Request $request, NewsPost $newsPost): RedirectResponse
    {
        $newsPost->is_published = ! $newsPost->is_published;

        if ($newsPost->is_published && $newsPost->published_at === null) {
            $newsPost->published_at = now();
        }

        $newsPost->save();
        $this->recordActivity(
            $request,
            $newsPost->is_published ? 'news_post.published' : 'news_post.unpublished',
            $newsPost,
            $newsPost->is_published ? 'L’actualité « '.$newsPost->title.' » a été publiée.' : 'L’actualité « '.$newsPost->title.' » a été dépubliée.',
        );

        return redirect()->route('management.content.news.index')->with('status', 'Le statut de publication a été mis à jour.');
    }

    public function destroyNews(Request $request, NewsPost $newsPost): RedirectResponse
    {
        $imagePath = $newsPost->image_path;
        $this->recordActivity($request, 'news_post.deleted', $newsPost, 'L’actualité « '.$newsPost->title.' » a été supprimée.');
        $newsPost->delete();

        if ($this->isManagedNewsImage($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()->route('management.content.news.index')->with('status', 'L’actualité a été supprimée.');
    }

    private function uniqueSlug(string $slug, string $title, ?NewsPost $except = null): string
    {
        $baseSlug = Str::slug($slug !== '' ? $slug : $title);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'actualite';
        $candidate = $baseSlug;
        $suffix = 2;

        while (NewsPost::query()->where('slug', $candidate)->when($except, fn ($query) => $query->where('id', '!=', $except->getKey()))->exists()) {
            $candidate = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function storeNewsImage(Request $request): string
    {
        $path = $request->file('image')?->store('news', 'public');

        if (! is_string($path)) {
            throw ValidationException::withMessages(['image' => 'L’image n’a pas pu être enregistrée. Réessayez.']);
        }

        return $path;
    }

    private function isManagedNewsImage(?string $path): bool
    {
        return $path !== null && str_starts_with($path, 'news/') && ! str_contains($path, '..');
    }

    private function recordActivity(Request $request, string $event, NewsPost|SitePage $subject, string $description): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'event' => $event,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
