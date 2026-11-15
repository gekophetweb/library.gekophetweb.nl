<?php

namespace App\Support\Appearance;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Background photos from Settings › Appearance and the MCP server. Kept on the private
 * `local` disk and served only through the signed, owner-only `appearance.photo` route.
 * With GD (the Docker image has it) every photo is re-encoded, which drops EXIF data and
 * anything that is not pixels, and scaled down to MAX_SIDE on its longest side.
 */
class AppearancePhotos
{
    public const int MAX_KILOBYTES = 4096;

    public const int MAX_SIDE = 2400;

    public const array MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const string DISK = 'local';

    /** Save a new photo, switch the background to it and remove the previous file. */
    public function replace(User $user, string $contents, ?int $veil = null): Appearance
    {
        $old = $user->appearance->photo;
        $changes = ['photo' => $this->store($user, $contents), 'background' => Background::Photo->value];

        if ($veil !== null) {
            $changes['veil'] = $veil;
        }

        $user->forceFill(['appearance' => $user->appearance->with($changes)])->save();
        $this->delete($old);

        return $user->appearance;
    }

    /** Back to the standard look, photo removed. */
    public function reset(User $user): Appearance
    {
        $old = $user->appearance->photo;
        $user->forceFill(['appearance' => null])->save();
        $this->delete($old);

        return $user->appearance;
    }

    public function path(User $user): ?string
    {
        $photo = $user->appearance->photo;

        return $photo !== null && Storage::disk(self::DISK)->exists($photo) ? $photo : null;
    }

    public function disk(): string
    {
        return self::DISK;
    }

    /** Remove every photo $user ever stored (account deletion). */
    public function deleteAll(User $user): void
    {
        Storage::disk(self::DISK)->deleteDirectory('appearance/'.$user->getKey());
    }

    private function store(User $user, string $contents): string
    {
        if (strlen($contents) > self::MAX_KILOBYTES * 1024) {
            throw new InvalidArgumentException('The image may be at most '.(self::MAX_KILOBYTES / 1024).' MB.');
        }

        $info = @getimagesizefromstring($contents);

        if ($info === false || ! in_array($info['mime'], self::MIME_TYPES, true)) {
            throw new InvalidArgumentException('The image must be a JPG, PNG or WebP file.');
        }

        [$contents, $extension] = extension_loaded('gd')
            ? $this->reencode($contents, $info[0], $info[1])
            : [$contents, $info['mime'] === 'image/jpeg' ? 'jpg' : Str::after($info['mime'], 'image/')];

        $path = 'appearance/'.$user->getKey().'/'.Str::random(32).'.'.$extension;
        Storage::disk(self::DISK)->put($path, $contents);

        return $path;
    }

    private function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** @return array{0: string, 1: string} */
    private function reencode(string $contents, int $width, int $height): array
    {
        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new InvalidArgumentException('The image could not be read.');
        }

        $scale = min(1, self::MAX_SIDE / max($width, $height, 1));

        if ($scale < 1) {
            $image = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        }

        $webp = function_exists('imagewebp');

        ob_start();
        $webp ? imagewebp($image, null, 82) : imagejpeg($image, null, 82);

        return [(string) ob_get_clean(), $webp ? 'webp' : 'jpg'];
    }
}
