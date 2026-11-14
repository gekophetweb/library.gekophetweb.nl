{{-- Hardcover: a personal API token. --}}
@php
    $step = 'pl-1 marker:font-medium marker:text-stone-500';
    $link = 'font-medium underline underline-offset-2';
@endphp
<div class="space-y-4 text-sm leading-relaxed text-stone-700 dark:text-stone-300">
    <p>Hardcover is a community book catalogue with good series data, used to find the series of translated manga.</p>
    <ol class="list-decimal space-y-2 pl-5">
        <li class="{{ $step }}">Create a free account at <a class="{{ $link }}" href="https://hardcover.app/" target="_blank" rel="noopener noreferrer">hardcover.app</a>, or sign in to the one you have.</li>
        <li class="{{ $step }}">Open <a class="{{ $link }}" href="{{ $provider->setupUrl() }}" target="_blank" rel="noopener noreferrer">Settings → Hardcover API</a> (hardcover.app/account/api).</li>
        <li class="{{ $step }}">Copy the token shown there and paste it below. With or without <code class="rounded bg-stone-100 px-1 py-0.5 font-mono text-xs dark:bg-stone-800">Bearer </code> in front both work.</li>
    </ol>
    <div class="rounded-md bg-stone-100 px-3 py-2 dark:bg-stone-800">
        <p class="font-medium">Good to know</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            <li>The token acts as your Hardcover account, so keep it to yourself.</li>
            <li>Hardcover tokens expire after a while. When series of translated manga stop being found, open the same page, copy the new token and save it here again.</li>
        </ul>
    </div>
</div>
