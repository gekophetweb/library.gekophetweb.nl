{{-- Rakuten Web Service: an application ID plus an access key (required since Rakuten's 2026 API move).
     The site is in Japanese; labels are quoted as shown there. $serverIp: config catalogue.providers.rakuten.server_ip. --}}
@php
    $step = 'pl-1 marker:font-medium marker:text-stone-500';
    $link = 'font-medium underline underline-offset-2';
    $mono = 'rounded bg-stone-100 px-1 py-0.5 font-mono text-xs dark:bg-stone-800';
@endphp
<div class="space-y-4 text-sm leading-relaxed text-stone-700 dark:text-stone-300">
    <p>Rakuten Books is the best source for Japanese editions. Rakuten needs <strong>two values</strong>: an <strong>application ID</strong> and an <strong>access key</strong>. Rakuten Web Service is in Japanese; your browser's translate button helps, and the labels to look for are quoted below (they may differ slightly).</p>

    <p class="rounded-md bg-amber-50 px-3 py-2 text-amber-900 dark:bg-amber-950 dark:text-amber-200"><strong>Registered an app before 2026?</strong> Rakuten moved to a new API in 2026; old application IDs (19 digits) no longer work. Register a new app as below.</p>

    <ol class="list-decimal space-y-2 pl-5">
        <li class="{{ $step }}">Open <a class="{{ $link }}" href="{{ $provider->setupUrl() }}" target="_blank" rel="noopener noreferrer">Rakuten Web Service</a> and sign in with a Rakuten account (<strong>ログイン</strong>). No account yet? Register one for free (<strong>楽天会員登録</strong>); an e-mail address is enough.</li>
        <li class="{{ $step }}">Register a new application: choose <strong>+ アプリID発行</strong> (issue a new app ID).</li>
        <li class="{{ $step }}">Fill in the form:
            <ul class="mt-1 list-disc space-y-1 pl-5">
                <li><strong>アプリ名</strong> (app name): anything, for example <em>My library</em>.</li>
                <li><strong>アプリURL</strong> (app URL) and, if asked, <strong>許可するWebサイト</strong> (allowed websites): this site's address.</li>
                <li><strong>許可するIPアドレス</strong> (allowed IP addresses), if asked: the address of this site's server, because the lookups come from there.
                    @if ($serverIp)
                        It is <code class="{{ $mono }}" data-server-ip>{{ $serverIp }}</code>.
                    @else
                        Ask the person who runs this site for it.
                    @endif
                </li>
            </ul>
            <x-copy-field :value="rtrim((string) config('app.url'), '/')" label="This site's address:" class="mt-2" />
            @if ($serverIp)
                <x-copy-field :value="$serverIp" label="This server's IP address:" class="mt-2" />
            @endif
        </li>
        <li class="{{ $step }}">Tick the box to accept the terms of use (<strong>規約に同意</strong>) and submit the form.</li>
        <li class="{{ $step }}">Rakuten shows your app's details. Copy two values and paste them below:
            <ul class="mt-1 list-disc space-y-1 pl-5">
                <li>the <strong>アプリID / applicationId</strong> (application ID), which looks like <code class="{{ $mono }}">xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code>;</li>
                <li>the <strong>accessKey</strong> (access key), which starts with <code class="{{ $mono }}">pk_</code>.</li>
            </ul>
        </li>
    </ol>
    <div class="rounded-md bg-stone-100 px-3 py-2 dark:bg-stone-800">
        <p class="font-medium">Good to know</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            <li>Do not paste the application secret or the affiliate ID (アフィリエイトID) shown on the same page.</li>
            <li>A new allowed IP address can take 5–10 minutes to start working at Rakuten.</li>
            <li>Rakuten answers <code class="{{ $mono }}">CLIENT_IP_NOT_ALLOWED</code> when the server's IP address is not allowed for your app, and <code class="{{ $mono }}">specify valid applicationId</code> for an old application ID or a missing access key.</li>
            <li>You can find both values again later under <strong>アプリ一覧</strong> (your apps). Rakuten limits each app to about one search per second, so use an app of your own rather than sharing one.</li>
        </ul>
    </div>
</div>
