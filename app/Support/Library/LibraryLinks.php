<?php

namespace App\Support\Library;

use App\Http\Middleware\ResolveLibraryShare;
use App\Models\Artist;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\LibraryShare;
use App\Models\Series;

/**
 * Where library links go: the owner's routes, or the read-only share routes when the
 * request came in through a share link. One set of views serves both. Stateless: it reads
 * the share back off the current request each time, so it is safe as a shared instance.
 */
final class LibraryLinks
{
    public function share(): ?LibraryShare
    {
        $share = request()->attributes->get(ResolveLibraryShare::ATTRIBUTE);

        return $share instanceof LibraryShare ? $share : null;
    }

    public function isShared(): bool
    {
        return $this->share() !== null;
    }

    /** Edit forms, ownership toggles, share buttons and anything else that writes. */
    public function canEdit(): bool
    {
        return ! $this->isShared();
    }

    /** False when the link's landing page is the only page above this one. */
    public function hasHome(): bool
    {
        return ! $this->isShared() || $this->share()->isWholeLibrary();
    }

    public function home(): string
    {
        return $this->isShared() ? route('share.index') : route('home');
    }

    /** @param  array<string, mixed>  $query */
    public function category(Category $category, array $query = []): ?string
    {
        if ($share = $this->share()) {
            return $share->exposes($category) ? route('share.category', ['category' => $category] + $query) : null;
        }

        return route('library.category', ['category' => $category] + $query);
    }

    public function series(Series $series): string
    {
        return $this->isShared() ? route('share.series', $series) : route('library.series', $series);
    }

    public function object(CollectionObject $object): string
    {
        return $this->isShared() ? route('share.object', $object) : route('objects.show', $object);
    }

    /** Artist pages are owner-only; a viewer sees the name as plain text. */
    public function artist(Artist $artist): ?string
    {
        return $this->isShared() ? null : route('library.artist', $artist);
    }
}
