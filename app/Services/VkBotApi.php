<?php

namespace App\Services;

use App\Models\BotButton;
use App\Models\Message;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VkBotApi implements IBotApi
{
    private string $token;

    public function __construct(string $token){
        $this->token = $token;
    }

    public function get_message(Request $request): Message|null
    {
        if($request['type'] == 'message_new' && $request['secret'] == Config::get('services.vkapi.secret')){
            $user = User::firstOrNew(['uid' => $request['object']['message']['peer_id'], 'type' => 'vk']);
            $user->save();

            $file_link = null;
            if(count($request['object']['message']['attachments']) > 0){
                $file = $request['object']['message']['attachments'][0];
                if($file['type'] === 'doc'){
                    $file_link = $file['doc']['url'];
                }
            }

            return new Message('vk', $user, $request['object']['message']['text'], file_link: $file_link);
        }
        if($request['type'] == 'message_event' && $request['secret'] == Config::get('services.vkapi.secret')){
            $user = User::firstOrNew(['uid' => $request['object']['peer_id'], 'type' => 'vk']);
            $user->save();
            return new Message('vk', $user, $request['object']['payload']['data'], $request['object']['event_id']);
        }
        return null;
    }

    public function send_message(User $user, string $text, array $keyboard = [], bool $is_inline_keyboard = false, ?string $callback_query_id = null): void
    {
        $compiled_keyboard = '';
        if(count($keyboard) > 0) $compiled_keyboard = $this->make_keyboard($keyboard, $is_inline_keyboard);
        $this->api_call('messages.send', ['peer_id' => $user->uid, 'random_id' => rand(1,10000), 'message' => $text, 'keyboard' => $compiled_keyboard]);
        if($callback_query_id != null) $this->api_call('messages.sendMessageEventAnswer', ['event_id' => $callback_query_id, 'user_id' => $user->uid, 'peer_id' => $user->uid]);
    }

    /**
     * @throws ConnectionException
     */
    public function api_call(string $method, array $params): PromiseInterface|Response
    {
        return Http::withHeader('Authorization', 'Bearer '.$this->token)->post('https://api.vk.com/method/'.$method.'?'.http_build_query($params).'&v=5.199');
    }

    public function make_keyboard(array $buttons, bool $is_inline): string
    {
        // Вк не берёт больше 10 кнопок, поэтому отфильтруем все необязательные. Костыль, но что поделать
        $totalCount = array_sum(array_map('count', $buttons));
        if($totalCount > 10){
            for($i = 0; $i < count($buttons); $i++){
                $buttons[$i] = array_filter($buttons[$i], function (BotButton $btn) {
                    return $btn->isRequired();
                });
            }
        }

        $final_buttons = [];
        for($i = 0; $i < count($buttons); $i++){
            foreach ($buttons[$i] as $button){
                $type = 'text';
                if($button->getUrl() !== null) $type = 'open_link';
                elseif($button->getCallbackData() !== '[]') $type = 'callback';
                $btn = [
                    'action' => [
                        'type' => $type,
                        'label' => $button->getText(),
                        'payload' => $button->getCallbackData(),
                    ],
//                    'color' => $button->getColor(),
                ];
                if($button->getUrl() !== null) $btn['action']['link'] = $button->getUrl();
                $final_buttons[$i][] = $btn;
            }
        }
        $keyboard = [
            'one_time' => false,
            'inline' => $is_inline,
            'buttons' => $final_buttons,
        ];
        return json_encode($keyboard);
    }

    public function get_type(): string
    {
        return 'vk';
    }

    public function get_user_name(User $user): string
    {
        try{
            $api = $this->api_call('users.get', ['user_ids' => $user->uid, 'lang' => 'ru']);
            return $api['response'][0]['first_name'];
        } catch (\Exception $exception){
            return '';
        }
    }
}
