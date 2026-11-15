<?php

namespace App\Support\Appearance;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * One user's own look (plan redesign-themes §3), stored as `users.appearance` JSON.
 * Every option has a default, so a missing or unknown value never breaks a page; share
 * links always render self::defaults(), never the visitor's or the owner's settings.
 */
final readonly class Appearance
{
    public const int VEIL_MIN = 60;

    public const int VEIL_MAX = 95;

    public const int VEIL_DEFAULT = 85;

    /** The options a user (or the MCP server) may set; `photo` only changes through an upload. */
    public const array SETTABLE = ['mode', 'accent', 'background', 'veil', 'headingFont', 'density'];

    public function __construct(
        public ThemeMode $mode = ThemeMode::System,
        public AccentColour $accent = AccentColour::Stamp,
        public Background $background = Background::Ruled,
        public ?string $photo = null,
        public int $veil = self::VEIL_DEFAULT,
        public HeadingFont $headingFont = HeadingFont::Bricolage,
        public Density $density = Density::Comfortable,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    /**
     * Lenient on purpose: unknown values fall back to the default, the veil is clamped,
     * and a photo background without a stored photo shows the ruled card.
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];
        $photo = is_string($data['photo'] ?? null) && $data['photo'] !== '' ? $data['photo'] : null;
        $background = Background::tryFrom((string) ($data['background'] ?? '')) ?? Background::Ruled;

        return new self(
            mode: ThemeMode::tryFrom((string) ($data['mode'] ?? '')) ?? ThemeMode::System,
            accent: AccentColour::tryFrom((string) ($data['accent'] ?? '')) ?? AccentColour::Stamp,
            background: $background === Background::Photo && $photo === null ? Background::Ruled : $background,
            photo: $photo,
            veil: max(self::VEIL_MIN, min(self::VEIL_MAX, (int) ($data['veil'] ?? self::VEIL_DEFAULT))),
            headingFont: HeadingFont::tryFrom((string) ($data['headingFont'] ?? '')) ?? HeadingFont::Bricolage,
            density: Density::tryFrom((string) ($data['density'] ?? '')) ?? Density::Comfortable,
        );
    }

    /**
     * Validation for the settable options; each is optional so a partial change (the
     * theme toggle, an MCP call) keeps the rest.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'mode' => ['sometimes', 'string', Rule::enum(ThemeMode::class)],
            'accent' => ['sometimes', 'string', Rule::enum(AccentColour::class)],
            'background' => ['sometimes', 'string', Rule::enum(Background::class)],
            'veil' => ['sometimes', 'integer', 'between:'.self::VEIL_MIN.','.self::VEIL_MAX],
            'headingFont' => ['sometimes', 'string', Rule::enum(HeadingFont::class)],
            'density' => ['sometimes', 'string', Rule::enum(Density::class)],
        ];
    }

    /**
     * A copy with the given (already validated) options changed.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        $allowed = array_flip([...self::SETTABLE, 'photo']);

        return self::fromArray([...$this->toArray(), ...array_intersect_key($changes, $allowed)]);
    }

    /** @return array{mode: string, accent: string, background: string, photo: ?string, veil: int, headingFont: string, density: string} */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'accent' => $this->accent->value,
            'background' => $this->background->value,
            'photo' => $this->photo,
            'veil' => $this->veil,
            'headingFont' => $this->headingFont->value,
            'density' => $this->density->value,
        ];
    }

    public function isDefault(): bool
    {
        return $this->toArray() === self::defaults()->toArray();
    }

    /** Signed, owner-only URL of the stored photo; it changes whenever the photo does. */
    public function photoUrl(User $owner): ?string
    {
        return $this->photo === null ? null : URL::signedRoute('appearance.photo', [
            'user' => $owner,
            'v' => substr(hash('sha256', $this->photo), 0, 12),
        ]);
    }

    /**
     * Attributes for <html>: the CSS in resources/css/app.css keys off these. `data-theme`
     * is the resolved light/dark value; for "same as device" an inline script in the
     * layout replaces the server's guess before the first paint.
     *
     * @return array<string, string>
     */
    public function htmlAttributes(?User $owner = null): array
    {
        $attributes = [
            'data-theme' => $this->mode === ThemeMode::Dark ? 'dark' : 'light',
            'data-theme-mode' => $this->mode->value,
            'data-accent' => $this->accent->value,
            'data-bg' => $this->background->value,
            'data-font' => $this->headingFont->value,
            'data-density' => $this->density->value,
        ];

        $photoUrl = $this->background === Background::Photo && $owner !== null ? $this->photoUrl($owner) : null;

        if ($photoUrl !== null) {
            $attributes['style'] = sprintf('--photo: url(%s); --veil: %d%%;', json_encode($photoUrl, JSON_UNESCAPED_SLASHES), $this->veil);
        } elseif ($this->background === Background::Photo) {
            $attributes['data-bg'] = Background::Ruled->value;
        }

        return $attributes;
    }

    /**
     * The settings plus every allowed value with its label: what the MCP tools return.
     *
     * @return array{settings: array<string, mixed>, options: array<string, mixed>}
     */
    public function describe(): array
    {
        $settings = $this->toArray();
        $settings['hasPhoto'] = $this->photo !== null;
        unset($settings['photo']);

        return ['settings' => $settings, 'options' => self::options()];
    }

    /** @return array<string, mixed> */
    public static function options(): array
    {
        $list = fn (array $cases): array => array_map(
            fn (ThemeMode|Background|HeadingFont|Density $case) => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );

        return [
            'mode' => $list(ThemeMode::cases()),
            'accent' => array_map(fn (AccentColour $accent) => [
                'value' => $accent->value,
                'label' => $accent->label(),
                'light' => $accent->light(),
                'dark' => $accent->dark(),
            ], AccentColour::cases()),
            'background' => $list(Background::cases()),
            'veil' => [
                'min' => self::VEIL_MIN,
                'max' => self::VEIL_MAX,
                'default' => self::VEIL_DEFAULT,
                'note' => 'How much theme colour is laid over the photo (percent), so text stays readable.',
            ],
            'headingFont' => $list(HeadingFont::cases()),
            'density' => $list(Density::cases()),
        ];
    }
}
