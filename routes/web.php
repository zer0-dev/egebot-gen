<?php

use App\Services\BotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

$appname = Config::get('app.name');

Route::post("/$appname/vk", function (Request $request, BotService $botService){
    if($request['type'] == 'confirmation'){
        return response(Config::get('services.vkapi.confirmation_code'));
    }
    $botService->handle_message($request);
    return response('ok');
});

Route::post("/$appname/tg", function (Request $request, BotService $botService){
    $botService->handle_message($request);
});

Route::post("/$appname/max", function (Request $request, BotService $botService){
    $botService->handle_message($request);
});
