@props(['title' => null])
{{-- The admin section: the normal page frame plus its own menu (a side column on wide
     screens, a scrollable row of tabs on phones). --}}
@php
    $newSuggestions = App\Models\Suggestion::query()->where('status', App\Enums\SuggestionStatus::New)->count();
    $items = [
        ['admin.home', 'admin.home', 'Overview', null],
        ['admin.users.index', 'admin.users.*', 'Users', null],
        ['admin.sso.index', 'admin.sso.*', 'Sign-in', null],
        ['admin.languages.index', 'admin.languages.*', 'Languages', null],
        ['admin.categories.index', 'admin.categories.*', 'Categories', null],
        ['admin.scan-batches.index', 'admin.scan-batches.*', 'Scan batches', null],
        ['admin.suggestions.index', 'admin.suggestions.*', 'Suggestions', $newSuggestions ?: null],
    ];
@endphp
<x-layout :title="$title ? $title.' · Admin' : 'Admin'" wide>
    <div class="grid gap-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-12">
        <x-section-menu label="Admin" :items="$items" />

        <div class="min-w-0">
            {{ $slot }}
        </div>
    </div>
</x-layout>
