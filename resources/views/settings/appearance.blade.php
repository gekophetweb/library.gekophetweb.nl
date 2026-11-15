@use('App\Support\Appearance\AccentColour')
@use('App\Support\Appearance\Background')
@use('App\Support\Appearance\Density')
@use('App\Support\Appearance\HeadingFont')
@use('App\Support\Appearance\ThemeMode')
<x-settings-layout title="Appearance">
    @php
        $legend = 'mb-3 font-display text-lg font-bold';
        $value = fn (string $key, $default) => old($key, $default);
    @endphp

    <div class="mb-8 space-y-1.5">
        <h1 class="font-display text-4xl leading-none font-extrabold sm:text-[44px]">Appearance</h1>
        <p class="text-muted">Make the library yours. Only you see this; people you share with get the standard look.</p>
    </div>

    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <form method="POST" action="{{ route('settings.appearance.update') }}" enctype="multipart/form-data" class="card space-y-7 p-6" data-appearance-form>
            @csrf
            @method('PUT')

            <fieldset>
                <legend class="{{ $legend }}">Theme</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach (ThemeMode::cases() as $mode)
                        <label class="chip">
                            <input type="radio" name="mode" value="{{ $mode->value }}" class="sr-only" @checked($value('mode', $appearance->mode->value) === $mode->value)>
                            {{ $mode->label() }}
                        </label>
                    @endforeach
                </div>
                @error('mode') <p class="mt-2 text-sm text-accent">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset>
                <legend class="{{ $legend }} mb-1">Accent colour</legend>
                <p class="mb-3 text-sm text-muted">Each colour has a light and a dark shade that stay readable.</p>
                <div class="flex flex-wrap gap-3">
                    @foreach (AccentColour::cases() as $accent)
                        <label class="group flex w-[4.5rem] cursor-pointer flex-col items-center gap-1.5 text-center text-xs font-medium">
                            <input type="radio" name="accent" value="{{ $accent->value }}" class="peer sr-only" @checked($value('accent', $appearance->accent->value) === $accent->value)>
                            <span class="block size-11 rounded-full shadow-[inset_0_0_0_1px_rgba(0,0,0,.12)] peer-checked:shadow-[0_0_0_3px_var(--surface),0_0_0_5px_var(--ink)] peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-[var(--accent)]"
                                  style="background: linear-gradient(135deg, {{ $accent->light() }} 50%, {{ $accent->dark() }} 50%)"></span>
                            {{ $accent->label() }}
                        </label>
                    @endforeach
                </div>
                @error('accent') <p class="mt-2 text-sm text-accent">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset>
                <legend class="{{ $legend }}">Background</legend>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(7rem,1fr))] gap-3">
                    @foreach (Background::cases() as $background)
                        <label class="tile">
                            <input type="radio" name="background" value="{{ $background->value }}" class="sr-only" @checked($value('background', $appearance->background->value) === $background->value)>
                            <span class="paper swatch block h-14 rounded-lg border border-line" data-bg="{{ $background->value }}"
                                  @if ($background === Background::Photo) style="--photo: {{ $photoUrl ? 'url('.json_encode($photoUrl, JSON_UNESCAPED_SLASHES).')' : 'repeating-linear-gradient(90deg, #7a3b2e 0 12px, #2f4a5c 12px 20px, #c9a46a 20px 28px, #3e5b3a 28px 42px, #8c6f52 42px 50px, #1f2a3a 50px 62px)' }}; --veil: 55%" @endif></span>
                            <span class="text-[13px] font-semibold">{{ $background->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('background') <p class="mt-2 text-sm text-accent">{{ $message }}</p> @enderror

                <div class="mt-4 space-y-4 rounded-xl border border-dashed border-line-strong p-4" data-photo-fields>
                    @if ($photoUrl)
                        <div class="flex items-center gap-3">
                            <img src="{{ $photoUrl }}" alt="Your current background photo" class="h-16 w-24 rounded-lg border border-line object-cover" data-current-photo>
                            <p class="text-sm text-muted">Your current photo. Choose a new one to replace it.</p>
                        </div>
                    @endif
                    <div>
                        <label for="photo" class="mb-1 block text-[13px] font-semibold">Your image <span class="font-normal text-muted">· JPG, PNG or WebP, up to 4 MB</span></label>
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                               class="block w-full text-sm text-muted file:mr-3 file:min-h-10 file:cursor-pointer file:rounded-[10px] file:border file:border-line-strong file:bg-surface file:px-3 file:text-sm file:font-medium file:text-ink">
                        @error('photo') <p class="mt-2 text-sm text-accent">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="veil" class="mb-1 flex justify-between text-[13px] font-semibold">Soften the image so text stays readable <output for="veil" class="font-mono" data-veil-output>{{ $value('veil', $appearance->veil) }}%</output></label>
                        <input id="veil" name="veil" type="range" min="{{ $options['veil']['min'] }}" max="{{ $options['veil']['max'] }}" step="5" value="{{ $value('veil', $appearance->veil) }}" class="w-full accent-[var(--accent)]">
                        @error('veil') <p class="mt-2 text-sm text-accent">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="{{ $legend }}">Heading font</legend>
                <div class="grid grid-cols-3 gap-3">
                    @foreach (HeadingFont::cases() as $font)
                        <label class="tile items-start p-3">
                            <input type="radio" name="headingFont" value="{{ $font->value }}" class="sr-only" @checked($value('headingFont', $appearance->headingFont->value) === $font->value)>
                            <span class="text-3xl leading-none font-extrabold" style="font-family: {{ $font->stack() }}">Aa</span>
                            <span class="text-[13px] font-semibold">{{ $font->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('headingFont') <p class="mt-2 text-sm text-accent">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset>
                <legend class="{{ $legend }}">Density</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach (Density::cases() as $density)
                        <label class="chip">
                            <input type="radio" name="density" value="{{ $density->value }}" class="sr-only" @checked($value('density', $appearance->density->value) === $density->value)>
                            {{ $density->label() }}
                        </label>
                    @endforeach
                </div>
                @error('density') <p class="mt-2 text-sm text-accent">{{ $message }}</p> @enderror
            </fieldset>

            <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
                <button class="btn btn-primary">Save appearance</button>
                <button form="appearance-reset" class="btn btn-ghost" @disabled($appearance->isDefault())>Back to the standard look</button>
            </div>
        </form>

        <form id="appearance-reset" method="POST" action="{{ route('settings.appearance.reset') }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>

        <aside class="space-y-5 lg:sticky lg:top-6">
            <section aria-label="Preview" class="space-y-3">
                <p class="label-mono">Preview · this page follows your picks</p>
                @foreach (['light', 'dark'] as $theme)
                    <figure data-theme="{{ $theme }}" data-accent="{{ $appearance->accent->value }}" data-preview class="paper overflow-hidden rounded-2xl border border-line shadow-card" data-bg="{{ $appearance->background->value }}" style="--margin-x: 22px">
                        <div class="flex items-center gap-2 border-b border-line bg-surface px-3 py-1.5">
                            <img src="{{ asset('images/gekophetweb-logo.png') }}" alt="" width="52" height="35" class="logo h-auto w-[52px]">
                            <span class="label-mono text-[10px]">{{ ucfirst($theme) }}</span>
                        </div>
                        <div class="space-y-3 py-4 pr-3 pl-9">
                            <p class="font-display text-xl font-extrabold text-ink">Your library</p>
                            <div class="grid grid-cols-2 gap-2">
                                <span class="rounded-xl border border-line bg-surface p-2.5"><span class="block font-display text-sm font-bold text-ink">Books</span><span class="text-xs text-muted">412 owned</span></span>
                                <span class="rounded-xl border border-line bg-surface p-2.5"><span class="block font-display text-sm font-bold text-ink">Music</span><span class="text-xs text-muted">186 owned</span></span>
                            </div>
                            <span class="flex flex-wrap gap-1.5"><span class="pill pill-owned">Owned</span><span class="pill pill-wanted">Wanted</span><span class="pill pill-accent">Not found</span></span>
                            <span class="btn btn-primary min-h-9 text-xs">Scan items</span>
                        </div>
                    </figure>
                @endforeach
            </section>

            <section class="card space-y-2 p-5" data-mcp-info>
                <h2 class="font-display text-lg font-bold">Style it with an AI assistant</h2>
                <p class="text-sm text-muted">
                    Connect an assistant that speaks MCP (such as Claude) to this address and it can read and change your
                    appearance, photo included. Sign it in with a token from
                    <a href="{{ route('settings.tokens') }}" class="text-accent underline">Phone app</a> as a bearer token.
                </p>
                <x-copy-field :value="$mcpUrl" />
            </section>
        </aside>
    </div>
</x-settings-layout>
