<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ManualSection extends Model
{
    protected $fillable = [
        'slug', 'title', 'summary', 'body', 'sort_order', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(ManualImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Render this section's Markdown body to HTML for display.
     */
    public function bodyHtml(): string
    {
        return static::renderMarkdown($this->body);
    }

    /**
     * The single place Markdown becomes HTML. Raw HTML in the source is stripped
     * rather than escaped, so the output is safe to inject with v-html even if an
     * admin account is compromised.
     */
    public static function renderMarkdown(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Build a URL-safe slug that no other section is using.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'section';
        $slug = $base;
        $n    = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
