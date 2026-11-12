@props(['title'])
<x-layout :title="$title">
    <div class="mx-auto mt-8 max-w-sm rounded-xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900">
        <h1 class="mb-6 text-xl font-semibold">{{ $title }}</h1>
        {{ $slot }}
    </div>
</x-layout>
