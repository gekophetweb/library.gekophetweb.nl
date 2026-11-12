<?php

namespace App\Providers;

use App\Enums\SearchScope;
use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use App\Services\Auth\SocialiteSsoDriverFactory;
use App\Services\Auth\SsoDriverFactory;
use App\Support\Library\LibraryLinks;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SsoDriverFactory::class, SocialiteSsoDriverFactory::class);
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->is_admin);

        // Short morph names for share scopes. Not enforced: Sanctum's token morph is
        // outside this map.
        Relation::morphMap(['category' => Category::class, 'series' => Series::class]);

        // Views link to owner or share routes through this; it is stateless (it reads
        // the share off the current request), so one instance serves every request.
        View::share('links', new LibraryLinks);

        // Searching the external catalogues costs provider calls; searching your own
        // library does not.
        RateLimiter::for('catalogue-search', fn (Request $request) => $request->input('scope') === SearchScope::Catalogue->value
            ? Limit::perMinute(20)->by('catalogue-search:'.($request->user()?->id ?? $request->ip()))
            : Limit::none());
    }
}
