<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;

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
        // Share tier icons with ALL views
        try {
            $tierIcons = [];
            $tierData = DB::table('coin_wallets')
                ->leftJoin('agent_tiers', 'coin_wallets.current_tier', '=', 'agent_tiers.tier_key')
                ->select('coin_wallets.agent_name', 'agent_tiers.icon as tier_icon', 'agent_tiers.tier_name', 'agent_tiers.color as tier_color')
                ->get();
            foreach ($tierData as $td) {
                $tierIcons[$td->agent_name] = [
                    'icon' => $td->tier_icon ?? '🤖',
                    'name' => $td->tier_name ?? 'Seedling',
                    'color' => $td->tier_color ?? '#8b9467'
                ];
            }
            View::share('tierIcons', $tierIcons);
        } catch (\Exception $e) {
            View::share('tierIcons', []);
        }
        //
    }
}
