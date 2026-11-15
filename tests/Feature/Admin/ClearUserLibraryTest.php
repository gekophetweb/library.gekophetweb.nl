<?php

use App\Enums\ApiKeyProvider;
use App\Models\ApiCallLog;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\LibraryShare;
use App\Models\ScanBatch;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use App\Models\UserApiKey;
use Database\Seeders\CatalogueSeeder;

/*
 * An admin empties a user's library: every scan batch with its codes, every object in
 * the collection and on the wishlist, and the user's series. The account stays.
 */

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create(['name' => 'Ann']);
    $this->books = Category::query()->whereNull('parent_id')->where('slug', 'books')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();
});

/** A scan batch of $user with one code per barcode. */
function clearTestBatch(User $user, string $status, array $codes = ['9784592135746']): ScanBatch
{
    $batch = new ScanBatch(['category_id' => test()->books->id, 'language_id' => test()->ja->id, 'ownership' => 'owned', 'status' => $status]);
    $batch->forceFill(['user_id' => $user->id])->save();

    foreach ($codes as $code) {
        $batch->items()->create(['barcode' => $code, 'status' => $status === ScanBatch::DONE ? 'added' : 'pending']);
    }

    return $batch;
}

/** Batches, owned and wished objects, a series with volumes and some shares for $user. */
function fillLibrary(User $user): array
{
    $series = Series::factory()->for($user)->create();
    SeriesVolume::factory()->count(2)->for($series)->create();
    $owned = CollectionObject::factory()->for($user)->create(['category_id' => test()->books->id, 'serie_id' => $series->id]);
    $wished = CollectionObject::factory()->for($user)->wishlisted()->create(['category_id' => test()->books->id]);
    $batch = clearTestBatch($user, ScanBatch::DONE, ['111', '222']);
    $batch->items()->first()->update(['object_id' => $owned->id]);

    return compact('series', 'owned', 'wished', 'batch');
}

it('removes every batch, object and series of the user, and keeps the account', function () {
    $ann = fillLibrary($this->user);
    $bob = fillLibrary(User::factory()->create());
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'ann-key']);
    $wholeLibrary = LibraryShare::factory()->for($this->user)->create();
    $seriesShare = LibraryShare::factory()->forSeries($ann['series'])->create();
    $log = ApiCallLog::query()->create([
        'user_id' => $this->user->id, 'scan_batch_item_id' => $ann['batch']->items()->value('id'), 'provider' => 'rakuten',
        'operation' => 'lookup', 'query' => 'isbn=111', 'outcome' => 'success', 'status_code' => 200, 'duration_ms' => 50,
    ]);

    $this->actingAs($this->admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.library.destroy', $this->user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status', 'Removed 2 items, 1 scan batch and 1 series from Ann’s library.');

    expect(CollectionObject::query()->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and(ScanBatch::query()->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and(Series::query()->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and(SeriesVolume::query()->where('series_id', $ann['series']->id)->exists())->toBeFalse()
        ->and($seriesShare->fresh())->toBeNull()
        // The account, its keys, a whole-library share and the API call log stay.
        ->and($this->user->fresh())->not->toBeNull()
        ->and($this->user->apiKey(ApiKeyProvider::Rakuten))->toBe('ann-key')
        ->and($wholeLibrary->fresh())->not->toBeNull()
        ->and($log->fresh()->scan_batch_item_id)->toBeNull()
        // Someone else's library is untouched.
        ->and(CollectionObject::query()->where('user_id', $bob['owned']->user_id)->count())->toBe(2)
        ->and($bob['batch']->fresh())->not->toBeNull()
        ->and($bob['series']->fresh())->not->toBeNull();
});

it('shows the button only for users with something to remove', function () {
    fillLibrary($this->user);
    $empty = User::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('data-clear-library="'.$this->user->id.'"', false)
        ->assertSee(route('admin.users.library.destroy', $this->user))
        ->assertDontSee('data-clear-library="'.$empty->id.'"', false);
});

it('lets an admin empty their own library, keeping their account and admin role', function () {
    $mine = fillLibrary($this->admin);

    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertSee('data-clear-library="'.$this->admin->id.'"', false);

    $this->from(route('admin.users.index'))
        ->delete(route('admin.users.library.destroy', $this->admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status', 'Removed 2 items, 1 scan batch and 1 series from '.$this->admin->name.'’s library.');

    expect($mine['owned']->fresh())->toBeNull()
        ->and($this->admin->scanBatches()->exists())->toBeFalse()
        ->and($this->admin->fresh()->is_admin)->toBeTrue();
    $this->get(route('admin.users.index'))->assertOk()->assertDontSee('data-clear-library="'.$this->admin->id.'"', false);
});

it('refuses while one of the user\'s batches is still running', function (string $status) {
    $ann = fillLibrary($this->user);
    clearTestBatch($this->user, $status);

    $this->actingAs($this->admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.library.destroy', $this->user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors(['library' => 'Ann has a scan batch that is still adding items. Try again when it is done.']);

    expect($ann['owned']->fresh())->not->toBeNull()
        ->and(ScanBatch::query()->where('user_id', $this->user->id)->count())->toBe(2);
})->with([ScanBatch::QUEUED, ScanBatch::PROCESSING]);

it('is for admins only', function () {
    $ann = fillLibrary($this->user);

    $this->delete(route('admin.users.library.destroy', $this->user))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->delete(route('admin.users.library.destroy', $this->user))->assertForbidden();
    $this->actingAs($this->user)->delete(route('admin.users.library.destroy', $this->user))->assertForbidden();

    expect($ann['owned']->fresh())->not->toBeNull();
});
