<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The languages offered when scanning and editing items. The code is fixed once created
 * (catalogue lookups pick providers by it); the name can change. A language is only
 * removed while nothing uses it, since deleting would cascade to that data.
 */
class LanguageController extends Controller
{
    public function index(): View
    {
        return view('admin.languages.index', [
            'languages' => Language::query()->withCount(['objects', 'artists', 'scanBatches'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => Str::lower(trim((string) $request->input('code')))]);

        $validated = $request->validateWithBag('create', [
            // ISO 639-1 ("ja") or a tag with a region / script ("zh-hant", "pt-br").
            'code' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/', Rule::unique('languages', 'code')],
            'name' => ['required', 'string', 'max:100', Rule::unique('languages', 'name')],
        ], [
            'code.regex' => 'Use a language code like "ja", "en" or "zh-hant".',
        ]);

        $language = Language::query()->create($validated);

        return redirect()->route('admin.languages.index')->with('status', "{$language->name} added.");
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $validated = $request->validateWithBag("language-{$language->id}", [
            'name' => ['required', 'string', 'max:100', Rule::unique('languages', 'name')->ignore($language)],
        ]);

        $language->update($validated);

        return redirect()->route('admin.languages.index')->with('status', "{$language->name} saved.");
    }

    public function destroy(Language $language): RedirectResponse
    {
        $language->loadCount(['objects', 'artists', 'scanBatches']);

        if ($uses = self::uses($language)) {
            throw ValidationException::withMessages(['language' => "{$language->name} is still used by {$uses}, so it can't be removed."]);
        }

        $language->delete();

        return redirect()->route('admin.languages.index')->with('status', "{$language->name} removed.");
    }

    /** What uses $language, in words ("3 items and 1 scan batch"), or null. Needs the counts loaded. */
    public static function uses(Language $language): ?string
    {
        $parts = array_filter([
            $language->objects_count ? $language->objects_count.' '.Str::plural('item', $language->objects_count) : null,
            $language->artists_count ? $language->artists_count.' '.Str::plural('artist', $language->artists_count) : null,
            $language->scan_batches_count ? $language->scan_batches_count.' '.Str::plural('scan batch', $language->scan_batches_count) : null,
        ]);

        return $parts === [] ? null : Str::of(implode(', ', $parts))->replaceLast(', ', ' and ')->toString();
    }
}
