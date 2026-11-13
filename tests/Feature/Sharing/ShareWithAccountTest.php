<?php

use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\LibraryShare;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->owner = User::factory()->create(['name' => 'Jaap', 'email' => 'jaap@example.com']);
    $this->friend = User::factory()->create(['email' => 'friend@example.com']);
    $this->books = Category::query()->where('slug', 'books')->sole();
    CollectionObject::factory()->for($this->owner)->create(['category_id' => $this->books->id, 'name' => 'Kafka on the Shore']);
});

function personalShare(array $attributes = []): LibraryShare
{
    return LibraryShare::factory()->for(test()->owner)->create(['recipient_email' => 'friend@example.com'] + $attributes);
}

it('creates a share for one e-mail address, stored lower-cased', function () {
    $this->actingAs($this->owner)
        ->post(route('settings.shares.store'), ['scope' => 'library', 'recipient_email' => ' Friend@Example.com '])
        ->assertRedirect(route('settings.edit').'#shares')
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'friend@example.com'));

    expect(LibraryShare::query()->sole()->recipient_email)->toBe('friend@example.com');

    $this->get(route('settings.edit'))->assertSee('Only for friend@example.com');
});

it('rejects an invalid address and the owner\'s own', function () {
    $this->actingAs($this->owner);

    $this->post(route('settings.shares.store'), ['scope' => 'library', 'recipient_email' => 'not-an-email'])->assertSessionHasErrors('recipient_email');
    $this->post(route('settings.shares.store'), ['scope' => 'library', 'recipient_email' => 'JAAP@example.com'])->assertSessionHasErrors('recipient_email');
    expect(LibraryShare::query()->count())->toBe(0);
});

it('opens for the recipient, read only', function () {
    $share = personalShare();

    $this->actingAs($this->friend)->get($share->url())
        ->assertOk()
        ->assertSee('Jaap’s collection')
        ->assertSee('Books');
    $this->get(route('share.category', ['shareToken' => $share->token, 'category' => $this->books]))
        ->assertOk()
        ->assertSee('Kafka on the Shore')
        ->assertDontSee('Share Books');
});

it('sends a guest to the login page and back', function () {
    $share = personalShare();

    $this->get($share->url())->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe($share->url());
});

it('is a 404 for any other account, the owner included', function () {
    $share = personalShare();

    $this->actingAs(User::factory()->create())->get($share->url())->assertNotFound();
    $this->actingAs($this->owner)->get($share->url())->assertNotFound();
});

it('keeps plain link shares open to anyone', function () {
    $share = LibraryShare::factory()->for($this->owner)->create();

    $this->get($share->url())->assertOk();
    $this->actingAs($this->friend)->get($share->url())->assertOk();
});

it('lists usable personal shares on the recipient\'s dashboard', function () {
    $share = personalShare(['label' => 'For you']);
    personalShare(['expires_at' => now()->subDay(), 'label' => 'Lapsed one']);
    LibraryShare::factory()->for($this->owner)->create(['label' => 'Public link']);

    $this->actingAs($this->friend)->get(route('home'))
        ->assertSee('Shared with you')
        ->assertSee($share->url())
        ->assertSee('For you')
        ->assertDontSee('Lapsed one')
        ->assertDontSee('Public link');

    $this->actingAs(User::factory()->create())->get(route('home'))->assertDontSee('data-shared-with-me', false);
});

it('matches the recipient case-insensitively', function () {
    $share = personalShare();
    $this->friend->forceFill(['email' => 'FRIEND@example.com'])->save();

    $this->actingAs($this->friend)->get($share->url())->assertOk();
});
