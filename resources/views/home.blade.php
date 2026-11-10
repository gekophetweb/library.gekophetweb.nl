<x-layout title="Home">
    <h1 class="text-2xl font-semibold">Hello, {{ auth()->user()->name }}</h1>
    <p class="mt-2 text-stone-600 dark:text-stone-400">Your library will show up here.</p>
</x-layout>
