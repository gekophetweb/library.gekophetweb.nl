{{--
    Step-by-step set-up for each provider type, next to the provider form.
    $callbackUrl: the provider's callback URL, or null before it is saved (the URL
    contains the provider's id). JavaScript shows only the guide for the selected type.
--}}
@php
    $appUrl = rtrim((string) config('app.url'), '/');
    $publicUrl = str_starts_with($appUrl, 'https://');
    $step = 'pl-1 marker:font-medium marker:text-stone-500';
    $link = 'font-medium underline underline-offset-2';
    $mono = 'rounded bg-stone-100 px-1 py-0.5 font-mono text-xs dark:bg-stone-800';
@endphp

<div class="space-y-4 text-sm leading-relaxed text-stone-700 dark:text-stone-300">
    @if ($callbackUrl)
        <x-copy-field :value="$callbackUrl" label="Callback / redirect URL for this provider:" />
    @else
        <p class="rounded-md border border-dashed border-stone-300 px-3 py-2 text-stone-500 dark:border-stone-700">
            The callback URL appears here once you <strong>save</strong> the provider: it contains the provider's number.
            You can save with the client ID and secret first and add the callback URL at the provider afterwards.
        </p>
    @endif

    @unless ($publicUrl)
        <p class="rounded-md bg-amber-50 px-3 py-2 text-amber-900 dark:bg-amber-950 dark:text-amber-200">
            <strong>APP_URL is {{ $appUrl }}.</strong> Most providers only accept an <code>https://</code> callback on your
            public hostname. Set <code>APP_URL</code> to your public <code>https://</code> address in <code>.env</code> and recreate the
            containers (<code>docker compose up -d</code>) before registering the callback URL.
        </p>
    @endunless

    {{-- Google --------------------------------------------------------------------- --}}
    <section data-sso-guide="google" class="space-y-3 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
        <h2 class="text-base font-semibold text-stone-900 dark:text-stone-100">Set up Google</h2>
        <ol class="list-decimal space-y-2 pl-5">
            <li class="{{ $step }}">Open the <a class="{{ $link }}" href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a> and create a project (or pick one).</li>
            <li class="{{ $step }}">Go to <strong>APIs &amp; Services → OAuth consent screen</strong> (Google Auth Platform). Fill in <strong>Branding</strong>: an app name and support e-mail.</li>
            <li class="{{ $step }}">Under <strong>Audience</strong>, choose <strong>External</strong>. Either add the Google accounts that may sign in as <strong>test users</strong>, or click <strong>Publish app</strong> so any Google account can. The scopes this app uses (<code class="{{ $mono }}">openid email profile</code>) need no verification by Google.</li>
            <li class="{{ $step }}">Go to <strong>Clients → Create client</strong>, application type <strong>Web application</strong>.</li>
            <li class="{{ $step }}">Under <strong>Authorized redirect URIs</strong>, add the callback URL shown above. Leave <em>Authorized JavaScript origins</em> empty.</li>
            <li class="{{ $step }}">Click <strong>Create</strong> and copy the <strong>Client ID</strong> and <strong>Client secret</strong> into this form. Leave Tenant, Issuer URL and Scopes empty.</li>
        </ol>
        <p class="text-stone-500">Google can take a few minutes to apply a new redirect URI. <code class="{{ $mono }}">redirect_uri_mismatch</code> means the URI at Google differs from the one above; <em>"Access blocked"</em> means the account is not a test user and the app is not published.</p>
    </section>

    {{-- Microsoft ------------------------------------------------------------------ --}}
    <section data-sso-guide="microsoft" class="space-y-3 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
        <h2 class="text-base font-semibold text-stone-900 dark:text-stone-100">Set up Microsoft (Entra ID)</h2>
        <ol class="list-decimal space-y-2 pl-5">
            <li class="{{ $step }}">Open the <a class="{{ $link }}" href="https://entra.microsoft.com/" target="_blank" rel="noopener">Microsoft Entra admin center</a> → <strong>Applications → App registrations → New registration</strong>.</li>
            <li class="{{ $step }}">
                Pick the <strong>supported account types</strong>; this decides what goes in <strong>Tenant</strong> here:
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>Only your organisation → the <strong>Directory (tenant) ID</strong> from the app's Overview page.</li>
                    <li>Any organisation → <code class="{{ $mono }}">organizations</code></li>
                    <li>Organisations and personal Microsoft accounts → <code class="{{ $mono }}">common</code></li>
                </ul>
            </li>
            <li class="{{ $step }}">Under <strong>Redirect URI</strong>, choose platform <strong>Web</strong> and enter the callback URL shown above. Click <strong>Register</strong>.</li>
            <li class="{{ $step }}">From the <strong>Overview</strong> page, copy the <strong>Application (client) ID</strong> into <strong>Client ID</strong>.</li>
            <li class="{{ $step }}">Go to <strong>Certificates &amp; secrets → New client secret</strong>. Copy the secret's <strong>Value</strong> (not the Secret ID) into <strong>Client secret</strong>. Note the expiry date: sign-in stops working when it expires, so create a new one before then.</li>
            <li class="{{ $step }}">Leave Issuer URL and Scopes empty (the default is <code class="{{ $mono }}">openid email profile</code>).</li>
        </ol>
        <p class="text-stone-500">Microsoft does not confirm that an e-mail address is verified, so a Microsoft login is never linked to an existing account automatically. Existing users sign in another way first and link Microsoft under <strong>Settings → Linked sign-in methods</strong>.</p>
    </section>

    {{-- GitHub --------------------------------------------------------------------- --}}
    <section data-sso-guide="github" class="space-y-3 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
        <h2 class="text-base font-semibold text-stone-900 dark:text-stone-100">Set up GitHub</h2>
        <ol class="list-decimal space-y-2 pl-5">
            <li class="{{ $step }}">On GitHub, open <a class="{{ $link }}" href="https://github.com/settings/developers" target="_blank" rel="noopener">Settings → Developer settings → OAuth Apps</a> → <strong>New OAuth app</strong>. (For an organisation: the organisation's Settings → Developer settings.)</li>
            <li class="{{ $step }}">Application name: anything, e.g. the site name. <strong>Homepage URL</strong>: <code class="{{ $mono }}">{{ $appUrl }}</code>.</li>
            <li class="{{ $step }}"><strong>Authorization callback URL</strong>: the callback URL shown above. Leave <em>Enable Device Flow</em> off. Click <strong>Register application</strong>.</li>
            <li class="{{ $step }}">Copy the <strong>Client ID</strong>, then click <strong>Generate a new client secret</strong> and copy it straight away (GitHub shows it once).</li>
            <li class="{{ $step }}">Leave Tenant, Issuer URL and Scopes empty.</li>
        </ol>
        <p class="text-stone-500">GitHub signs people in with their primary, verified e-mail address. An OAuth app has a single callback URL; use a separate app per site.</p>
    </section>

    {{-- Generic OIDC --------------------------------------------------------------- --}}
    <section data-sso-guide="oidc" class="space-y-3 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
        <h2 class="text-base font-semibold text-stone-900 dark:text-stone-100">Set up OpenID Connect (Authentik, Keycloak, …)</h2>
        <ol class="list-decimal space-y-2 pl-5">
            <li class="{{ $step }}">
                In your identity provider, create an OAuth2 / OpenID Connect client:
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li><strong>Authentik:</strong> Applications → Providers → <em>OAuth2/OpenID Provider</em>, client type <strong>Confidential</strong>; then an Application that uses it.</li>
                    <li><strong>Keycloak:</strong> your realm → Clients → <em>Create client</em>, type OpenID Connect, <strong>Client authentication</strong> on, <em>Standard flow</em> on.</li>
                </ul>
            </li>
            <li class="{{ $step }}">Add the callback URL shown above as the <strong>redirect URI</strong> (Keycloak: <em>Valid redirect URIs</em>).</li>
            <li class="{{ $step }}">Copy the <strong>client ID</strong> and <strong>client secret</strong> into this form.</li>
            <li class="{{ $step }}">
                <strong>Issuer URL</strong>: the address the provider's <code class="{{ $mono }}">/.well-known/openid-configuration</code> lives under, for example:
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>Authentik: <code class="{{ $mono }} break-all">https://auth.example.com/application/o/&lt;application-slug&gt;/</code></li>
                    <li>Keycloak: <code class="{{ $mono }} break-all">https://keycloak.example.com/realms/&lt;realm&gt;</code></li>
                </ul>
                Open <em>issuer</em> + <code class="{{ $mono }}">/.well-known/openid-configuration</code> in a browser to check it: it must show a JSON document.
            </li>
            <li class="{{ $step }}">Scopes: leave empty for <code class="{{ $mono }}">openid email profile</code>.</li>
        </ol>
        <p class="text-stone-500">A login is linked to an existing account by e-mail only when the provider sends <code class="{{ $mono }}">email_verified: true</code>. Check that your provider marks addresses as verified; otherwise users link it themselves under <strong>Settings → Linked sign-in methods</strong>.</p>
    </section>

    <p class="text-stone-500">After saving, use <strong>Test login</strong> on the overview: it signs in at the provider and only reports what came back, without changing any account.</p>
</div>
