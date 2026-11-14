{{-- Google Books: a Google Cloud API key with the Books API enabled. --}}
@php
    $step = 'pl-1 marker:font-medium marker:text-stone-500';
    $link = 'font-medium underline underline-offset-2';
@endphp
<div class="space-y-4 text-sm leading-relaxed text-stone-700 dark:text-stone-300">
    <p>Google Books covers most editions outside Japan and helps find series of translated manga. The key comes from the Google Cloud Console; no billing account or credit card is needed.</p>
    <ol class="list-decimal space-y-2 pl-5">
        <li class="{{ $step }}">Open the <a class="{{ $link }}" href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud Console</a> and sign in with a Google account. The first time, accept the terms of service.</li>
        <li class="{{ $step }}">Create a project: click the project picker at the top → <strong>New project</strong>, give it any name (for example <em>My library</em>) and click <strong>Create</strong>. Make sure the new project is selected.</li>
        <li class="{{ $step }}">Open <a class="{{ $link }}" href="{{ $provider->setupUrl() }}" target="_blank" rel="noopener noreferrer">APIs &amp; Services → Library → Books API</a> and click <strong>Enable</strong>.</li>
        <li class="{{ $step }}">Go to <a class="{{ $link }}" href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">APIs &amp; Services → Credentials</a> and choose <strong>+ Create credentials → API key</strong>.</li>
        <li class="{{ $step }}">Restrict the key (optional, but wise): open the key, and under <strong>API restrictions</strong> choose <strong>Restrict key</strong> and tick only <strong>Books API</strong>. Save.</li>
        <li class="{{ $step }}">Copy the key (it starts with <code class="rounded bg-stone-100 px-1 py-0.5 font-mono text-xs dark:bg-stone-800">AIza</code>) and paste it below.</li>
    </ol>
    <div class="rounded-md bg-stone-100 px-3 py-2 dark:bg-stone-800">
        <p class="font-medium">Good to know</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            <li>Under <strong>Application restrictions</strong>, leave <strong>None</strong> (or choose <strong>IP addresses</strong> with this server's address). Do not pick <em>Websites</em>: lookups come from this server, not from your browser, so a website restriction blocks them.</li>
            <li>The free daily quota is far more than a personal library needs.</li>
            <li>“API has not been used in project … or it is disabled”: the Books API is not enabled in the project the key belongs to (step 3), or Google needs a few more minutes.</li>
            <li>“API key not valid”: the key was copied incompletely, or deleted in the console.</li>
        </ul>
    </div>
</div>
