<?php

namespace App\Http\Controllers;

use App\Models\ManualSection;
use Inertia\Inertia;
use Inertia\Response;

class ManualController extends Controller
{
    /**
     * The public user manual. Readable by guests and signed-in users alike.
     */
    public function index(): Response
    {
        $sections = ManualSection::published()
            ->with('images')
            ->ordered()
            ->get();

        return Inertia::render('manual/Index', [
            'sections' => $sections->map(fn (ManualSection $section) => [
                'slug'      => $section->slug,
                'title'     => $section->title,
                'summary'   => $section->summary,
                'body_html' => $section->bodyHtml(),
                'images'    => $section->images->map(fn ($image) => [
                    'url'     => $image->url,
                    'caption' => $image->caption,
                ])->all(),
            ])->all(),
            'updatedAt' => $sections->max('updated_at')?->toDateString(),
        ]);
    }
}
