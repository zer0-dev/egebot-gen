<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use App\Services\IBotApi;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaxBotApi implements IBotApi{

    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function get_message(Request $request): Message|null
    {
        if($request['update_type'] == 'bot_started' && $request->header('X-Max-Bot-Api-Secret') == Config::get('services.maxapi.secret')){
            $user = User::firstOrNew(['uid' => $request['user']['user_id'], 'type' => 'max']);
            $user->username = $request['user']['first_name'];
            $user->save();
            return new Message('max', $user, '/start');
        }
        if($request['update_type'] == 'message_created' && $request->header('X-Max-Bot-Api-Secret') == Config::get('services.maxapi.secret')){
            $user = User::firstOrNew(['uid' => $request['message']['sender']['user_id'], 'type' => 'max']);
            $user->username = $request['message']['sender']['first_name'];
            $user->save();

            $file_link = null;
            if(array_key_exists('attachments', $request['message']['body'])){
                $file = $request['message']['body']['attachments'][0];
                if($file['type'] === 'file'){
                    $file_link = $file['payload']['url'];
                }
            }

            return new Message('max', $user, $request['message']['body']['text'], file_link: $file_link);
        }
        if($request['update_type'] == 'message_callback' && $request->header('X-Max-Bot-Api-Secret') == Config::get('services.maxapi.secret')){
            $user = User::firstOrNew(['uid' => $request['callback']['user']['user_id'], 'type' => 'max']);
            $user->username = $request['callback']['user']['first_name'];
            $user->save();
            return new Message('max', $user, json_decode($request['callback']['payload'])->data, $request['callback']['callback_id']);
        }
        return null;
    }

    public function send_message(User $user, string $text, array $keyboard = [], bool $is_inline_keyboard = false, ?string $callback_query_id = null): void
    {
        $body = [
            'text' => $text,
        ];
        if(count($keyboard) > 0) $body['attachments'] = [$this->make_keyboard($keyboard)];
        $this->api_call('messages', ['user_id' => $user->uid], $body);
        if($callback_query_id != null) $this->api_call('answers', ['callback_id' => $callback_query_id], []);
    }

    /**
     * @throws ConnectionException
     */
    public function api_call(string $method, array $params, array $data): PromiseInterface|Response
    {
        return Http::withHeader('Authorization', $this->token)->post('https://platform-api.max.ru/'.$method.'?'.http_build_query($params), $data);
    }

    public function make_keyboard(array $buttons, bool $is_inline = true): array
    {
        $final_buttons = [];
        for($i = 0; $i < count($buttons); $i++){
            foreach ($buttons[$i] as $button){
                $type = 'message';
                if($button->getUrl() !== null) $type = 'link';
                elseif($button->getCallbackData() !== '[]') $type = 'callback';
                $btn = [
                    'type' => $type,
                    'text' => $button->getText(),
                    'payload' => $button->getCallbackData(),
                ];
                if($button->getUrl() !== null) $btn['url'] = $button->getUrl();
                $final_buttons[$i][] = $btn;
            }
        }
        return [
            'type' => 'inline_keyboard',
            'payload' => [
                'buttons' => $final_buttons
            ],
        ];
    }

    public function get_type(): string
    {
        return 'max';
    }

    public function get_user_name(User $user): string
    {
        return $user->username;
    }
}
