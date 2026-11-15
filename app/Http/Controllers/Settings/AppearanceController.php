<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAppearanceRequest;
use App\Models\User;
use App\Support\Appearance\Appearance;
use App\Support\Appearance\AppearancePhotos;
use App\Support\Appearance\ThemeMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Settings › Appearance (plan redesign-themes §3): each user's own look. */
class AppearanceController extends Controller
{
    public function __construct(private readonly AppearancePhotos $photos) {}

    public function edit(Request $request): View
    {
        return view('settings.appearance', [
            'appearance' => $request->user()->appearance,
            'options' => Appearance::options(),
            'photoUrl' => $request->user()->appearance->photoUrl($request->user()),
            'mcpUrl' => url('api/mcp/appearance'),
        ]);
    }

    public function update(UpdateAppearanceRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->hasFile('photo')) {
            try {
                $this->photos->replace($user, (string) $request->file('photo')->get());
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['photo' => $e->getMessage()])->withInput();
            }
        }

        $user->forceFill(['appearance' => $user->appearance->with($request->options())])->save();

        return redirect()->route('settings.appearance')->with('status', 'Appearance saved.');
    }

    /** The sun/moon button in the top bar: saves light, dark or "same as device". */
    public function theme(Request $request): JsonResponse|RedirectResponse
    {
        $mode = ThemeMode::from($request->validate([
            'mode' => ['required', 'string', Rule::enum(ThemeMode::class)],
        ])['mode']);

        $user = $request->user();
        $user->forceFill(['appearance' => $user->appearance->with(['mode' => $mode->value])])->save();

        return $request->expectsJson()
            ? response()->json(['mode' => $mode->value])
            : back()->with('status', 'Theme set to '.strtolower($mode->label()).'.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->photos->reset($request->user());

        return redirect()->route('settings.appearance')->with('status', 'Back to the standard look.');
    }

    /** The background photo: signed URL, and only ever for its owner. */
    public function photo(Request $request, User $user): StreamedResponse
    {
        abort_unless($request->user()?->is($user), 404);

        $path = $this->photos->path($user) ?? abort(404);

        return Storage::disk($this->photos->disk())->response($path, headers: [
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
