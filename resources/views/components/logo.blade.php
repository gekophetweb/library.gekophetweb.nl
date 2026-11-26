@props(['size' => 36, 'alt' => config('app.name')])
{{-- The app icon (same artwork as the favicon): a book, a record and a game cartridge. --}}
<img src="{{ asset('favicon.svg') }}" alt="{{ $alt }}" width="{{ $size }}" height="{{ $size }}" {{ $attributes->merge(['class' => 'shrink-0']) }}>
