<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLibraryShareRequest;
use App\Models\LibraryShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The owner's read-only share links: create one, or revoke it (delete the row). */
class LibraryShareController extends Controller
{
    public function store(StoreLibraryShareRequest $request): RedirectResponse
    {
        $share = new LibraryShare([
            'label' => $request->validated('label'),
            // End of the chosen day, so "expires 1 Dec" still works on 1 Dec.
            'expires_at' => $request->validated('expires_at') ? now()->parse($request->validated('expires_at'))->endOfDay() : null,
        ]);
        $share->user()->associate($request->user());

        if ($shareable = $request->shareable()) {
            $share->shareable()->associate($shareable);
        }

        $share->save();

        return redirect()->to(route('settings.edit').'#shares')
            ->with('status', 'Share link created: anyone with the link can view it, read only.')
            ->with('share_url', $share->url());
    }

    public function destroy(Request $request, LibraryShare $share): RedirectResponse
    {
        abort_unless($share->user_id === $request->user()->id, 404);

        $share->delete();

        return redirect()->to(route('settings.edit').'#shares')->with('status', 'Share link revoked. It no longer works.');
    }
}
