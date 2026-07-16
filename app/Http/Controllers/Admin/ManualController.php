<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualImage;
use App\Models\ManualSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ManualController extends Controller
{
    // ── Editor ───────────────────────────────────────────────────────────────

    public function index(): Response
    {
        return Inertia::render('admin/manual/Index', [
            'sections' => ManualSection::with('images')->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:150'],
            'summary'      => ['nullable', 'string', 'max:300'],
            'body'         => ['nullable', 'string', 'max:50000'],
            'is_published' => ['boolean'],
        ]);

        $section = ManualSection::create([
            ...$data,
            'slug'       => ManualSection::uniqueSlug($data['title']),
            'sort_order' => (int) ManualSection::max('sort_order') + 1,
        ]);

        return back()->with('success', "Section \"{$section->title}\" created.");
    }

    public function update(Request $request, ManualSection $section): RedirectResponse
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:150'],
            'summary'      => ['nullable', 'string', 'max:300'],
            'body'         => ['nullable', 'string', 'max:50000'],
            'is_published' => ['boolean'],
        ]);

        // Keep the slug (and any links pointing at it) stable once published.
        if ($section->title !== $data['title'] && ! $section->is_published) {
            $data['slug'] = ManualSection::uniqueSlug($data['title'], $section->id);
        }

        $section->update($data);

        return back()->with('success', "Section \"{$section->title}\" saved.");
    }

    public function destroy(ManualSection $section): RedirectResponse
    {
        $title = $section->title;

        // The images row cascades on delete, but the files on disk do not.
        Storage::disk('public')->delete($section->images->pluck('path')->all());
        $section->delete();

        return back()->with('success', "Section \"{$title}\" deleted.");
    }

    /**
     * Persist a new section order. Ids arrive in the order they should display.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:manual_sections,id'],
        ]);

        foreach ($data['ids'] as $position => $id) {
            ManualSection::whereKey($id)->update(['sort_order' => $position + 1]);
        }

        return back()->with('success', 'Section order updated.');
    }

    // ── Images ───────────────────────────────────────────────────────────────

    public function uploadImage(Request $request, ManualSection $section): RedirectResponse
    {
        $data = $request->validate([
            'image'   => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
            'caption' => ['nullable', 'string', 'max:200'],
        ]);

        $section->images()->create([
            'path'       => $request->file('image')->store("manual/{$section->id}", 'public'),
            'caption'    => $data['caption'] ?? null,
            'sort_order' => (int) $section->images()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Image added.');
    }

    public function updateImage(Request $request, ManualImage $image): RedirectResponse
    {
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:200'],
        ]);

        $image->update($data);

        return back()->with('success', 'Caption saved.');
    }

    public function destroyImage(ManualImage $image): RedirectResponse
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Image removed.');
    }

    // ── Live preview ─────────────────────────────────────────────────────────

    /**
     * Render Markdown exactly as the public page will, so the editor preview can
     * never drift from what readers actually see.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:50000'],
        ]);

        return response()->json([
            'html' => ManualSection::renderMarkdown($data['body'] ?? ''),
        ]);
    }
}
