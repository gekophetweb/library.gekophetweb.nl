<?php

use App\Models\User;

it('lets an admin promote a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Ann']);

    $this->actingAs($admin)->from(route('admin.users.index'))
        ->patch(route('admin.users.role', $user), ['is_admin' => 1])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status', 'Ann is now an admin.');

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('lets an admin demote another admin', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.users.role', $other), ['is_admin' => 0])->assertRedirect();

    expect($other->fresh()->is_admin)->toBeFalse();
});

it('never lets an admin change their own role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.users.role', $admin), ['is_admin' => 0])->assertForbidden();

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('refuses non-admins', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)->patch(route('admin.users.role', $target), ['is_admin' => 1])->assertForbidden();

    expect($target->fresh()->is_admin)->toBeFalse();
});

it('validates the role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->patch(route('admin.users.role', $user), ['is_admin' => 'maybe'])
        ->assertSessionHasErrors('is_admin');
});

it('shows role buttons on every row but the admin\'s own', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create();
    User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

    expect(substr_count($response->getContent(), 'data-role-form'))->toBe(2);
    $response->assertSee('Make admin')->assertSee('Remove admin');
});
