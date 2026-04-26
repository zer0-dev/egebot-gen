<?php

namespace App\Providers;

use App\Services\BotService;
use App\Services\MaxBotApi;
use App\Services\PaymentService;
use App\Services\TgBotApi;
use App\Services\VkBotApi;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TgBotApi::class, function (Application $app){
            return new TgBotApi(config('services.tgapi.token'));
        });
        $this->app->singleton(VkBotApi::class, function (Application $app){
            return new VkBotApi(config('services.vkapi.access_token'));
        });

        $this->app->singleton(MaxBotApi::class, function (Application $app){
            return new MaxBotApi(config('services.maxapi.token'));
        });

        $this->app->singleton(PaymentService::class);

        $appname = Config::get('app.name');
        switch(Request::path()){
            case "$appname/vk":
                $this->app->singleton(BotService::class, function (Application $app){
                    $botService = new BotService();
                    $botApi = $app->make(VkBotApi::class);
                    $botService->setBotApi($botApi);
                    return $botService;
                });
                break;
            case "$appname/tg":
                $this->app->singleton(BotService::class, function (Application $app){
                    $botService = new BotService();
                    $botApi = $app->make(TgBotApi::class);
                    $botService->setBotApi($botApi);
                    return $botService;
                });
                break;
            case "$appname/max":
                $this->app->singleton(BotService::class, function (Application $app){
                    $botService = new BotService();
                    $botApi = $app->make(MaxBotApi::class);
                    $botService->setBotApi($botApi);
                    return $botService;
                });
                break;
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
