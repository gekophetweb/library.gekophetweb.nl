<?php

use App\Models\LibraryShare;
use App\Models\Series;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->series = Series::factory()->for($this->user)->create(['name' => 'ベルセルク', 'name_romanized' => 'Beruseruku']);
});

describe('editing', function () {
    it('lets the owner set an English name', function () {
        $this->actingAs($this->user)
            ->patch(route('library.series.update', $this->series), ['name_english' => 'Berserk'])
            ->assertRedirect(route('library.series', $this->series))
            ->assertSessionHas('status');

        expect($this->series->fresh()->name_english)->toBe('Berserk');
    });

    it('clears the English name when blank', function () {
        $this->series->update(['name_english' => 'Berserk']);

        $this->actingAs($this->user)->patch(route('library.series.update', $this->series), ['name_english' => '   ']);

        expect($this->series->fresh()->name_english)->toBeNull();
    });

    it('rejects a name over 255 characters', function () {
        $this->actingAs($this->user)
            ->patch(route('library.series.update', $this->series), ['name_english' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name_english');

        expect($this->series->fresh()->name_english)->toBeNull();
    });

    it('404s for another user\'s series', function () {
        $other = Series::factory()->create(['name' => 'ワンピース']);

        $this->actingAs($this->user)
            ->patch(route('library.series.update', $other), ['name_english' => 'One Piece'])
            ->assertNotFound();

        expect($other->fresh()->name_english)->toBeNull();
    });
});

describe('series page', function () {
    it('shows the English name as the title with the original underneath', function () {
        $this->series->update(['name_english' => 'Berserk']);

        $this->actingAs($this->user)->get(route('library.series', $this->series))
            ->assertSee('<title>Berserk ·', false)
            ->assertSeeInOrder(['<h1', 'Berserk', 'data-original-name', 'ベルセルク', 'Beruseruku'], false);
    });

    it('shows the original title alone without an English name', function () {
        $this->actingAs($this->user)->get(route('library.series', $this->series))
            ->assertSeeInOrder(['<h1', 'ベルセルク', 'Beruseruku'], false)
            ->assertDontSee('data-original-name', false);
    });

    it('shows the edit form to the owner only', function () {
        $this->series->update(['name_english' => 'Berserk']);

        $this->actingAs($this->user)->get(route('library.series', $this->series))
            ->assertSee('name="name_english"', false);

        auth()->logout();
        $share = LibraryShare::factory()->for($this->user)->create();

        $this->get(route('share.series', ['shareToken' => $share->token, 'series' => $this->series]))
            ->assertOk()
            ->assertSee('Berserk')
            ->assertDontSee('name="name_english"', false);
    });
});

it('sorts series by display name', function () {
    $this->series->update(['name_english' => 'Berserk']);
    $akira = Series::factory()->for($this->user)->create(['name' => 'Akira']);
    $kana = Series::factory()->for($this->user)->create(['name' => 'あ']);

    expect(Series::query()->orderByDisplayName()->pluck('id')->all())
        ->toBe([$akira->id, $this->series->id, $kana->id]);
});
