<?php

namespace App\Http\Controllers;

use App\Actions\Scan\AddScannedObject;
use App\Http\Requests\StoreWishlistItemRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Puts a catalogue search result on the wishlist, through the same create path as a
 * scan: a later scan of the ISBN in Own mode promotes it. Adding a book the user already
 * has (owned or wanted) returns that item instead of a duplicate.
 */
class WishlistController extends Controller
{
    public function store(StoreWishlistItemRequest $request, AddScannedObject $add): RedirectResponse
    {
        $object = $add->handle($request->user(), $request->setup(), $request->result());

        $status = match (true) {
            ! $object->wasRecentlyCreated && $object->isOwned() => "You already own {$object->name}.",
            ! $object->wasRecentlyCreated => "{$object->name} is already on your wishlist.",
            default => "Added {$object->name} to your wishlist.",
        };

        return back()->with('status', $status);
    }
}
