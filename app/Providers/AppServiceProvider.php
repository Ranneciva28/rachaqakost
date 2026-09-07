<?php

namespace App\Providers;

use App\Models\MediaFile;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('*', function ($view) {
            static $branding;

            if ($branding === null) {
                $branding = MediaFile::metadata()
                    ->whereIn('kind', ['LOGO', 'FAVICON'])
                    ->get()
                    ->keyBy('kind');
            }

            $view->with('brandLogo', $branding->get('LOGO'));
            $view->with('brandFavicon', $branding->get('FAVICON'));
        });
    }
}
