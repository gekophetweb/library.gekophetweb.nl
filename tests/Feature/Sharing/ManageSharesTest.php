<?php

use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\LibraryShare;
use App\Models\Series;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->series = Series::factory()->for($this->user)->create(['name' => 'ベルセルク']);
});

it('mints library, category and series links that show in settings', function () {
    $this->actingAs($this->user);

    $this->post(route('settings.shares.store'), ['scope' => 'library', 'label' => 'For my brother'])
        ->assertRedirect(route('settings.edit').'#shares')
        ->assertSessionHas('share_url');
    $this->post(route('settings.shares.store'), ['scope' => 'category:'.$this->books->id, 'expires_at' => now()->addWeek()->toDateString()]);
    $this->post(route('settings.shares.store'), ['scope' => 'series:'.$this->series->id]);

    $shares = LibraryShare::query()->orderBy('id')->get();
    expect($shares)->toHaveCount(3)
        ->and($shares[0]->isWholeLibrary())->toBeTrue()
        ->and($shares[0]->token)->toHaveLength(32)
        ->and($shares[1]->scopedCategory()->is($this->books))->toBeTrue()
        ->and($shares[1]->expires_at->isSameDay(now()->addWeek()))->toBeTrue()
        ->and($shares[2]->scopedSeries()->is($this->series))->toBeTrue();

    $this->get(route('settings.edit'))
        ->assertSee('For my brother')
        ->assertSee('Category: Books')
        ->assertSee('Series: ベルセルク')
        ->assertSee($shares[0]->url())
        ->assertSee('Never expires');
});

it('offers share buttons on the owner\'s category and series pages', function () {
    CollectionObject::factory()->for($this->user)->create(['category_id' => $this->books->id, 'serie_id' => $this->series->id]);

    $this->actingAs($this->user)->get(route('library.category', $this->books))
        ->assertSee('Share Books')->assertSee('value="category:'.$this->books->id.'"', false);
    $this->get(route('library.series', $this->series))
        ->assertSee('Share this series')->assertSee('value="series:'.$this->series->id.'"', false);
});

it('rejects a share for someone else\'s series, a sub-category, an unknown scope and a past expiry', function () {
    $this->actingAs($this->user);
    $manga = Category::query()->where('slug', 'manga')->sole();

    $this->post(route('settings.shares.store'), ['scope' => 'series:'.Series::factory()->create()->id])->assertSessionHasErrors('scope_id');
    $this->post(route('settings.shares.store'), ['scope' => 'category:'.$manga->id])->assertSessionHasErrors('scope_id');
    $this->post(route('settings.shares.store'), ['scope' => 'user:1'])->assertSessionHasErrors('scope_type');
    $this->post(route('settings.shares.store'), ['scope' => 'library', 'expires_at' => now()->subDay()->toDateString()])->assertSessionHasErrors('expires_at');

    expect(LibraryShare::count())->toBe(0);
});

it('revokes a link, after which it 404s, and another user cannot revoke it', function () {
    $share = LibraryShare::factory()->for($this->user)->create();
    $url = $share->url();

    $this->actingAs(User::factory()->create())->delete(route('settings.shares.destroy', $share))->assertNotFound();
    expect(LibraryShare::count())->toBe(1);

    $this->actingAs($this->user)->delete(route('settings.shares.destroy', $share))->assertRedirect();
    expect(LibraryShare::count())->toBe(0);

    auth()->logout();
    $this->get($url)->assertNotFound();
});

it('prunes links that lapsed over a month ago', function () {
    $old = LibraryShare::factory()->for($this->user)->create(['expires_at' => now()->subMonths(2)]);
    $recent = LibraryShare::factory()->for($this->user)->expired()->create();
    $open = LibraryShare::factory()->for($this->user)->create();

    Artisan::call('model:prune', ['--model' => [LibraryShare::class]]);

    expect(LibraryShare::query()->orderBy('id')->pluck('id')->all())->toBe([$recent->id, $open->id]);
});

it('schedules model pruning', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('model:prune');
});
