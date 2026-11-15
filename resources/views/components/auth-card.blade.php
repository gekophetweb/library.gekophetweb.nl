@props(['title'])
<x-layout :title="$title">
    <div class="flex flex-col items-center gap-3">
        @guest
            <div class="flex self-stretch justify-end">
                {{-- Guests have no account to save to: the choice stays in this browser. --}}
                <x-theme-toggle-button type="button" data-theme-toggle />
            </div>
            <img src="{{ asset('images/gekophetweb-logo.png') }}" alt="Gek op het Web" width="160" height="109" class="logo h-auto w-[132px] sm:w-[160px]">
            <p class="label-mono -mt-1 mb-4 text-xs tracking-[0.22em]">Library · books, music &amp; games</p>
        @endguest

        <div class="card w-full max-w-[420px] p-6 sm:p-8">
            <h1 class="mb-5 font-display text-[28px] leading-tight font-extrabold">{{ $title }}</h1>
            {{ $slot }}
        </div>
    </div>
</x-layout>
