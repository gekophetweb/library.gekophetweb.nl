<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SuggestionStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Language;
use App\Models\SsoProvider;
use App\Models\Suggestion;
use App\Models\User;
use App\Support\Auth\LoginSettings;
use Illuminate\View\View;

/** The admin section's start page: one card per area with its key numbers. */
class OverviewController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.overview', [
            'users' => User::query()->count(),
            'admins' => User::query()->where('is_admin', true)->count(),
            'providers' => SsoProvider::query()->enabled()->count(),
            'emailCodes' => LoginSettings::emailCodesEnabled(),
            'languages' => Language::query()->count(),
            'subCategories' => Category::query()->whereNotNull('parent_id')->count(),
            'topCategories' => Category::query()->whereNull('parent_id')->count(),
            'newSuggestions' => Suggestion::query()->where('status', SuggestionStatus::New)->count(),
        ]);
    }
}
