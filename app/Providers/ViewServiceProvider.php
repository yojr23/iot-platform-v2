<?php

namespace App\Providers;

use App\Models\Alert;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer([
            'layouts.partials.sidebar',
            'layouts.partials.navbar',
        ], function ($view) {
            static $unresolvedAlertsCount;

            if ($unresolvedAlertsCount === null) {
                $unresolvedAlertsCount = Alert::where('resolved', false)->count();
            }

            $view->with('unresolvedAlertsCount', $unresolvedAlertsCount);
        });
    }
}
