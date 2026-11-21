<?php

use App\Models\Artist;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

it('keeps every admin page away from non-admins and guests', function () {
    $pages = ['admin.home', 'admin.languages.index', 'admin.categories.index'];

    $this->actingAs(User::factory()->create());
    foreach ($pages as $page) {
        $this->get(route($page))->assertForbidden();
    }
    $this->post(route('admin.languages.store'), ['code' => 'fr', 'name' => 'French'])->assertForbidden();
    $this->post(route('admin.categories.store', Category::query()->where('slug', 'books')->sole()), ['name' => 'Comics'])->assertForbidden();
    $this->get(route('home'))->assertDontSee('data-admin-link', false);

    auth()->logout();
    $this->get(route('admin.home'))->assertRedirect(route('login'));
});

it('puts one Admin link in the header and the full menu in the admin section', function () {
    $this->actingAs($this->admin)->get(route('home'))
        ->assertSee('data-admin-link', false)
        ->assertDontSee(route('admin.users.index'))
        ->assertDontSee(route('admin.sso.index'));

    foreach (['admin.home', 'admin.users.index', 'admin.sso.index', 'admin.languages.index', 'admin.categories.index', 'admin.suggestions.index'] as $page) {
        $this->get(route($page))->assertOk()
            ->assertSee('aria-label="Admin"', false)
            ->assertSee(route('admin.languages.index'))
            ->assertSee(route('admin.categories.index'))
            ->assertSee('aria-current="page"', false);
    }
});

it('shows the version the running code was built from', function () {
    $this->actingAs($this->admin);

    config(['app.version' => '1.2.2']);
    $this->get(route('admin.users.index'))->assertOk()->assertSeeInOrder(['data-app-version', 'Version 1.2.2'], false);

    config(['app.version' => null]);
    $this->get(route('admin.home'))->assertOk()->assertSee('Version unknown');
});

it('shows each area with its numbers on the overview', function () {
    $this->actingAs($this->admin)->get(route('admin.home'))
        ->assertOk()
        ->assertSee('1 account')
        ->assertSee('4 languages')
        ->assertSee('3 main · 6 sub-categories');
});

it('adds, renames and removes a language', function () {
    $this->actingAs($this->admin);

    $this->post(route('admin.languages.store'), ['code' => ' FR ', 'name' => 'French'])->assertSessionHasNoErrors();
    $french = Language::query()->where('code', 'fr')->sole();

    $this->patch(route('admin.languages.update', $french), ['name' => 'Français', 'code' => 'xx'])->assertSessionHasNoErrors();
    expect($french->refresh()->name)->toBe('Français')->and($french->code)->toBe('fr');

    $this->delete(route('admin.languages.destroy', $french))->assertRedirect(route('admin.languages.index'));
    expect(Language::query()->where('code', 'fr')->exists())->toBeFalse();
});

it('validates new language codes and names', function () {
    $this->actingAs($this->admin);

    $this->post(route('admin.languages.store'), ['code' => 'ja', 'name' => 'Japanese again'])->assertSessionHasErrorsIn('create', 'code');
    $this->post(route('admin.languages.store'), ['code' => 'french', 'name' => 'French'])->assertSessionHasErrorsIn('create', 'code');
    $this->post(route('admin.languages.store'), ['code' => 'fr', 'name' => 'English'])->assertSessionHasErrorsIn('create', 'name');
    $this->post(route('admin.languages.store'), ['code' => 'zh-hant', 'name' => 'Chinese (Traditional)'])->assertSessionHasNoErrors();
});

it('refuses to remove a language that is in use, and says what uses it', function () {
    $japanese = Language::query()->where('code', 'ja')->sole();
    CollectionObject::factory()->count(2)->create()->each(fn (CollectionObject $object) => $object->languages()->attach($japanese));
    Artist::factory()->create()->languages()->attach($japanese);

    $this->actingAs($this->admin)->get(route('admin.languages.index'))->assertSee('Used by 2 items and 1 artist');

    $this->delete(route('admin.languages.destroy', $japanese))
        ->assertSessionHasErrors(['language' => 'Japanese is still used by 2 items and 1 artist, so it can\'t be removed.']);
    expect($japanese->exists())->toBeTrue();
});

it('adds, renames, reorders and removes a sub-category', function () {
    $books = Category::query()->whereNull('parent_id')->where('slug', 'books')->sole();
    $this->actingAs($this->admin);

    $this->post(route('admin.categories.store', $books), ['name' => 'Comics'])->assertSessionHasNoErrors();
    $comics = $books->children()->where('name', 'Comics')->sole();
    expect($comics->slug)->toBe('comics')->and($comics->kind)->toBe($books->kind)->and($comics->sort)->toBe(2);

    $this->patch(route('admin.categories.update', $comics), ['name' => 'Graphic novels', 'sort' => 0])->assertSessionHasNoErrors();
    // Renaming keeps the slug the API exposes.
    expect($comics->refresh()->name)->toBe('Graphic novels')->and($comics->slug)->toBe('comics')->and($comics->sort)->toBe(0);

    $this->post(route('admin.categories.store', $books), ['name' => 'Manga'])->assertSessionHasErrorsIn('create-'.$books->id, 'name');

    $this->delete(route('admin.categories.destroy', $comics))->assertRedirect(route('admin.categories.index'));
    expect(Category::query()->whereKey($comics->id)->exists())->toBeFalse();
});

it('gives a new sub-category a unique slug', function () {
    $music = Category::query()->whereNull('parent_id')->where('slug', 'music')->sole();
    Category::factory()->childOf($music)->create(['name' => 'Old CD', 'slug' => 'box-sets']);

    $this->actingAs($this->admin)->post(route('admin.categories.store', $music), ['name' => 'Box sets']);

    expect($music->children()->where('name', 'Box sets')->sole()->slug)->toBe('box-sets-2');
});

it('keeps main categories fixed and refuses to remove a sub-category in use', function () {
    $books = Category::query()->whereNull('parent_id')->where('slug', 'books')->sole();
    $manga = $books->children()->where('slug', 'manga')->sole();
    CollectionObject::factory()->create(['category_id' => $books->id, 'sub_category_id' => $manga->id]);
    (new ScanBatch([
        'category_id' => $books->id,
        'sub_category_id' => $manga->id,
        'language_id' => Language::query()->where('code', 'ja')->value('id'),
        'ownership' => 'owned',
    ]))->forceFill(['user_id' => $this->admin->id])->save();
    $this->actingAs($this->admin);

    $this->patch(route('admin.categories.update', $books), ['name' => 'Reading', 'sort' => 0])->assertNotFound();
    $this->delete(route('admin.categories.destroy', $books))->assertNotFound();
    $this->post(route('admin.categories.store', $manga), ['name' => 'Shonen'])->assertNotFound();

    $this->get(route('admin.categories.index'))->assertSee('Used by 1 item and 1 scan batch');
    $this->delete(route('admin.categories.destroy', $manga))->assertSessionHasErrors('category');
    expect($manga->exists())->toBeTrue();
});
