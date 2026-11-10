<?php

use App\Models\User;

it('shows every user to an admin, including themselves and the other admins', function () {
    $me = User::factory()->admin()->create(['name' => 'Me Admin']);
    User::factory()->admin()->create(['name' => 'Other Admin']);
    User::factory()->create(['name' => 'Plain User']);

    $this->actingAs($me)->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Me Admin')
        ->assertSee('Other Admin')
        ->assertSee('Plain User')
        ->assertSee('You');
});

it('lists admins first, then by name', function () {
    $me = User::factory()->admin()->create(['name' => 'Zed Admin']);
    User::factory()->create(['name' => 'Anna User']);
    User::factory()->admin()->create(['name' => 'Bob Admin']);

    $this->actingAs($me)->get(route('admin.users.index'))
        ->assertSeeInOrder(['Bob Admin', 'Zed Admin', 'Anna User']);
});

it('marks admins with a badge and only the signed-in admin with "You"', function () {
    $me = User::factory()->admin()->create(['name' => 'Me Admin']);
    $other = User::factory()->admin()->create(['name' => 'Other Admin']);

    $response = $this->actingAs($me)->get(route('admin.users.index'));

    expect(substr_count($response->getContent(), 'data-badge="admin"'))->toBe(2)
        ->and(substr_count($response->getContent(), 'data-badge="you"'))->toBe(1);
    $response->assertSee('data-user="'.$me->id.'" data-you', false);
    $response->assertDontSee('data-user="'.$other->id.'" data-you', false);
});

it('filters on admins or plain users', function () {
    // Rows, not names: the signed-in admin's name is also in the nav bar.
    $me = User::factory()->admin()->create();
    $plain = User::factory()->create();
    $row = fn (User $user) => 'data-user="'.$user->id.'"';

    $this->actingAs($me)->get(route('admin.users.index', ['role' => 'admins']))
        ->assertSee($row($me), false)->assertDontSee($row($plain), false);

    $this->get(route('admin.users.index', ['role' => 'users']))
        ->assertSee($row($plain), false)->assertDontSee($row($me), false);

    $this->get(route('admin.users.index', ['role' => 'everyone-else']))->assertSessionHasErrors('role');
});

it('paginates at 25 users', function () {
    $me = User::factory()->admin()->create();
    User::factory()->count(30)->create();

    $this->actingAs($me)->get(route('admin.users.index'))
        ->assertOk()
        ->assertViewHas('users', fn ($users) => $users->count() === 25 && $users->total() === 31);
});

it('forbids the list to non-admins and hides the nav link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    $this->get(route('home'))->assertDontSee(route('admin.users.index'));
});

it('shows the nav link to admins', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('home'))
        ->assertSee(route('admin.users.index'));
});

it('sends guests to the login page', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});
