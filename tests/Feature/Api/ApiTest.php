<?php

use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
});

it('creates a token in settings, shows it once and revokes it', function () {
    $this->actingAs($this->user)->post(route('settings.tokens.store'), ['name' => 'My phone'])
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('token');

    $plain = session('token');
    $token = $this->user->tokens()->sole();
    expect($token->name)->toBe('My phone')
        ->and($token->token)->not->toBe($plain);

    $this->get(route('settings.edit'))->assertSee('My phone');

    $this->flushSession();
    $this->app['auth']->forgetGuards();
    $this->withToken($plain)->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.email', $this->user->email);

    $this->app['auth']->forgetGuards();
    $this->actingAs($this->user)->delete(route('settings.tokens.destroy', $token->id))->assertRedirect(route('settings.edit'));
    expect($this->user->tokens()->count())->toBe(0);
});

it('does not let a user revoke someone else\'s token', function () {
    $other = User::factory()->create();
    $token = $other->createToken('theirs')->accessToken;

    $this->actingAs($this->user)->delete(route('settings.tokens.destroy', $token->id))->assertNotFound();
    expect($other->tokens()->count())->toBe(1);
});

it('answers 401 without a token', function () {
    $this->getJson('/api/v1/objects')->assertUnauthorized();
});

it('lists the category tree', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Books')
        ->assertJsonPath('data.0.kind', 'books')
        ->assertJsonPath('data.0.children.0.name', 'Manga');
});

it('lists only the user\'s objects, filtered and paginated', function () {
    Sanctum::actingAs($this->user);
    $series = Series::factory()->for($this->user)->create();
    CollectionObject::factory()->for($this->user)->create(['category_id' => $this->books->id, 'serie_id' => $series->id, 'name' => 'In series']);
    CollectionObject::factory()->for($this->user)->create(['category_id' => Category::query()->where('slug', 'music')->sole()->id, 'name' => 'A record']);
    CollectionObject::factory()->create(['name' => 'Not mine']);

    $this->getJson('/api/v1/objects')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissing(['name' => 'Not mine'])
        ->assertJsonStructure(['data' => [['id', 'barcode', 'name', 'name_romanized', 'name_english', 'category', 'sub_category', 'languages', 'genres', 'series', 'volume', 'creator', 'publisher', 'release_date']], 'meta' => ['total']]);

    $this->getJson('/api/v1/objects?category_id='.$this->books->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'In series');
    $this->getJson('/api/v1/objects?series_id='.$series->id)->assertJsonCount(1, 'data');
});

it('shows and updates an object, and 404s on someone else\'s', function () {
    Sanctum::actingAs($this->user);
    $object = CollectionObject::factory()->for($this->user)->create(['category_id' => $this->books->id]);
    $other = CollectionObject::factory()->create();

    $this->getJson("/api/v1/objects/{$object->id}")->assertOk()->assertJsonPath('data.id', $object->id);
    $this->getJson("/api/v1/objects/{$other->id}")->assertNotFound();
    $this->patchJson("/api/v1/objects/{$other->id}", ['name' => 'x'])->assertNotFound();

    $this->patchJson("/api/v1/objects/{$object->id}", [
        'name' => 'ベルセルク 2', 'name_romanized' => 'Berserk 2', 'languages' => [Language::query()->where('code', 'ja')->value('id')], 'volume' => '2',
    ])->assertOk()->assertJsonPath('data.name_romanized', 'Berserk 2')->assertJsonPath('data.volume', '2');

    $this->patchJson("/api/v1/objects/{$object->id}", ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors(['name', 'languages']);
});

it('lists series and shows one with its volumes', function () {
    Sanctum::actingAs($this->user);
    $series = Series::factory()->for($this->user)->create(['name' => 'ベルセルク', 'external_source' => 'ndl', 'external_id' => 'x']);
    SeriesVolume::factory()->for($series)->create(['volume_number' => '1', 'volume_sort' => 1, 'isbns' => ['9784592135746']]);
    SeriesVolume::factory()->for($series)->create(['volume_number' => '2', 'volume_sort' => 2]);
    CollectionObject::factory()->for($this->user)->create(['serie_id' => $series->id, 'barcode' => '9784592135746']);
    Series::factory()->create(['name' => 'Not mine']);

    $this->getJson('/api/v1/series')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.items_count', 1);

    $this->getJson("/api/v1/series/{$series->id}")
        ->assertOk()
        ->assertJsonPath('data.external_source', 'ndl')
        ->assertJsonPath('data.owned_count', 1)
        ->assertJsonPath('data.volumes.0.volume_number', '1')
        ->assertJsonPath('data.volumes.0.state', 'owned')
        ->assertJsonPath('data.volumes.1.state', 'missing');

    $this->getJson('/api/v1/series/'.Series::query()->where('name', 'Not mine')->value('id'))->assertNotFound();
});
