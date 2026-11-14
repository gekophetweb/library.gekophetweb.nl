{{-- Rakuten Web Service: an application ID. The site is in Japanese; the labels are quoted as shown there. --}}
@php
    $step = 'pl-1 marker:font-medium marker:text-stone-500';
    $link = 'font-medium underline underline-offset-2';
@endphp
<div class="space-y-4 text-sm leading-relaxed text-stone-700 dark:text-stone-300">
    <p>Rakuten Books is the best source for Japanese editions. Rakuten Web Service is in Japanese; your browser's translate button helps, and the labels to look for are quoted below.</p>
    <ol class="list-decimal space-y-2 pl-5">
        <li class="{{ $step }}">Open <a class="{{ $link }}" href="{{ $provider->setupUrl() }}" target="_blank" rel="noopener noreferrer">Rakuten Web Service</a> and sign in with a Rakuten account (<strong>ログイン</strong>). No account yet? Register one for free (<strong>楽天会員登録</strong>); an e-mail address is enough.</li>
        <li class="{{ $step }}">Choose <strong>+ アプリID発行</strong> (issue a new app ID).</li>
        <li class="{{ $step }}">Fill in the form:
            <ul class="mt-1 list-disc space-y-1 pl-5">
                <li><strong>アプリ名</strong> (app name): anything, for example <em>My library</em>.</li>
                <li><strong>アプリURL</strong> (app URL): this site's address.</li>
            </ul>
            <x-copy-field :value="rtrim((string) config('app.url'), '/')" class="mt-2" />
        </li>
        <li class="{{ $step }}">Tick the box to accept the terms of use (<strong>規約に同意</strong>) and submit the form.</li>
        <li class="{{ $step }}">Rakuten now shows your app's details. Copy the <strong>アプリID / applicationId</strong> and paste it below.</li>
    </ol>
    <div class="rounded-md bg-stone-100 px-3 py-2 dark:bg-stone-800">
        <p class="font-medium">Good to know</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            <li>Paste the <strong>application ID</strong>, not the application secret or the affiliate ID (アフィリエイトID) shown on the same page.</li>
            <li>You can find it again later under <strong>アプリ一覧</strong> (your apps) on Rakuten Web Service.</li>
            <li>Rakuten limits how fast one app ID may search, so use a key of your own rather than sharing one.</li>
        </ul>
    </div>
</div>
