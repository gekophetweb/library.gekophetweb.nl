<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sub-categories (Manga, Vinyl, …) under the fixed main categories. Books / Music / Games
 * each have their own detail table in code (CategoryKind), so they are not edited here.
 * The slug is set once on create and kept on rename: the API exposes it.
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->orderBy('sort')->orderBy('id')
                ->with(['children' => fn ($query) => $query->withCount(['subCategoryObjects', 'subCategoryScanBatches'])])
                ->get(),
        ]);
    }

    public function store(Request $request, Category $parent): RedirectResponse
    {
        abort_unless($parent->isTopLevel(), 404);

        $validated = $request->validateWithBag("create-{$parent->id}", [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->where('parent_id', $parent->id)],
        ], ['name.unique' => "{$parent->name} already has that sub-category."]);

        $category = $parent->children()->create([
            'kind' => $parent->kind,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($parent, $validated['name']),
            'sort' => (int) $parent->children()->max('sort') + 1,
        ]);

        return redirect()->route('admin.categories.index')->with('status', "{$category->name} added under {$parent->name}.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        abort_if($category->isTopLevel(), 404);

        $validated = $request->validateWithBag("category-{$category->id}", [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->where('parent_id', $category->parent_id)->ignore($category)],
            'sort' => ['required', 'integer', 'min:0', 'max:1000'],
        ], ['name.unique' => "{$category->parent->name} already has that sub-category."]);

        $category->update($validated);

        return redirect()->route('admin.categories.index')->with('status', "{$category->name} saved.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        abort_if($category->isTopLevel(), 404);

        $category->loadCount(['subCategoryObjects', 'subCategoryScanBatches']);

        if ($uses = self::uses($category)) {
            throw ValidationException::withMessages(['category' => "{$category->name} is still used by {$uses}, so it can't be removed."]);
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', "{$category->name} removed.");
    }

    /** What uses the sub-category, in words, or null. Needs the counts loaded. */
    public static function uses(Category $category): ?string
    {
        $parts = array_filter([
            $category->sub_category_objects_count ? $category->sub_category_objects_count.' '.Str::plural('item', $category->sub_category_objects_count) : null,
            $category->sub_category_scan_batches_count ? $category->sub_category_scan_batches_count.' '.Str::plural('scan batch', $category->sub_category_scan_batches_count) : null,
        ]);

        return $parts === [] ? null : implode(' and ', $parts);
    }

    private function uniqueSlug(Category $parent, string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;

        for ($i = 2; $parent->children()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
