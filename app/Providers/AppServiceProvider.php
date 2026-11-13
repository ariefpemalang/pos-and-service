<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('items')) {
                if (! \App\Models\Item::where('code', 'FLEXI O/F+VOL SAM A217')->exists()) {
                    (new \Database\Seeders\UserItemsSeeder)->run();
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore if database not ready
        }
    }
}
