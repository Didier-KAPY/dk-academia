<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePage extends Model
{
    public const DEFAULT_PAGES = [
        'about' => [
            'eyebrow' => 'QUI NOUS SOMMES',
            'title' => 'DK ACADEMIA, au service des compétences et des projets.',
            'introduction' => 'DK ACADEMIA accompagne les particuliers et les organisations en République démocratique du Congo grâce à la formation professionnelle et aux solutions informatiques.',
            'body' => 'Notre approche associe apprentissage pratique, accompagnement personnalisé et outils adaptés aux réalités du terrain.',
            'secondary_title' => null,
            'secondary_body' => null,
        ],
        'mission' => [
            'eyebrow' => 'NOTRE MISSION',
            'title' => 'Rendre les compétences utiles accessibles.',
            'introduction' => 'Nous transmettons des savoir-faire professionnels directement mobilisables et accompagnons les organisations dans leur évolution numérique.',
            'body' => '',
            'secondary_title' => 'Notre vision',
            'secondary_body' => 'Contribuer à un avenir où chacun peut apprendre, maîtriser de nouveaux outils et réussir durablement dans son activité.',
        ],
    ];

    protected $fillable = [
        'slug',
        'eyebrow',
        'title',
        'introduction',
        'body',
        'secondary_title',
        'secondary_body',
        'updated_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function forSlug(string $slug): self
    {
        abort_unless(array_key_exists($slug, self::DEFAULT_PAGES), 404);

        return self::query()->where('slug', $slug)->first()
            ?? new self(['slug' => $slug, ...self::DEFAULT_PAGES[$slug]]);
    }
}
