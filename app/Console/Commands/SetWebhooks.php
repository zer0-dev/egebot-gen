<?php

namespace App\Console\Commands;

use App\Services\MaxBotApi;
use App\Services\TgBotApi;
use App\Services\VkBotApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SetWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:set-webhooks {webhook}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Устанавливает вебхук для всех ботов';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $webhook = $this->argument('webhook').'/'.Config::get('app.name').'/';
        $vkBotApi = app(VkBotApi::class);
        $result = $vkBotApi->api_call('groups.getById', [])->json();
        if(array_key_exists('response', $result)){
            $group_id = $result['response']['groups'][0]['id'];
            $confirmation_code = $vkBotApi->api_call('groups.getCallbackConfirmationCode', ['group_id' => $group_id])['response']['code'];
            $this->setEnv('VK_CONFIRMATION_CODE', $confirmation_code);
            $result = $vkBotApi->api_call('groups.editCallbackServer', ['group_id' => $group_id, 'server_id' => 1, 'url' => $webhook.'vk', 'title' => 'bot', 'secret_key' => Config::get('services.vkapi.secret')]);
            if($result['response'] != '1'){
                echo "Ошибка ВК:\n".json_encode($result);
            } else {
                echo "Вебхук ВК установлен\n";
            }
        }

        $tgBotApi = app(TgBotApi::class);
        $result = $tgBotApi->api_call('setWebhook', ['url' => $webhook.'tg']);
        if(!$result['ok']){
            echo "Ошибка Телеграм:\n".json_encode($result);
        } else {
            echo "Вебхук Телеграм установлен\n";
        }

        $maxBotApi = app(MaxBotApi::class);
        $result = $maxBotApi->api_call('subscriptions', [], ['url' => $webhook.'max', 'update_types' => ["message_created", "bot_started", "message_callback"], 'secret' => Config::get('services.maxapi.secret')]);
        if(!$result['success']){
            echo "Ошибка Макс:\n".json_encode($result);
        } else {
            echo "Вебхук Макс установлен\n";
        }
    }

    private function setEnv($name, $value)
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            file_put_contents($path, str_replace(
                $name . '=' . env($name), $name . '=' . $value, file_get_contents($path)
            ));
        }
    }
}
