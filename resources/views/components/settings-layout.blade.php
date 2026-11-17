@props(['title' => null])
{{-- The settings section: one short page per topic, with its own menu (a side column on
     wide screens, a scrollable row of tabs on phones), like the admin section. --}}
@php
    $missingKeys = count(auth()->user()->missingScanKeys());
    $items = [
        ['settings.edit', 'settings.edit', 'Profile', null],
        ['settings.appearance', 'settings.appearance', 'Appearance', null],
        ['settings.api-keys', 'settings.api-keys*', 'API keys', $missingKeys ?: null],
        ['settings.tokens', 'settings.tokens', 'Phone app', null],
        ['settings.shares', 'settings.shares', 'Shared links', null],
        ['settings.sign-in', 'settings.sign-in', 'Sign-in methods', null],
        ['settings.account', 'settings.account', 'Delete account', null],
    ];
@endphp
<x-layout :title="$title ? $title.' · Settings' : 'Settings'" wide>
    <div class="grid gap-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-12">
        <x-section-menu label="Settings" :items="$items" badge="wanted" />

        <div class="min-w-0 max-w-3xl">
            {{ $slot }}
        </div>
    </div>
</x-layout>
