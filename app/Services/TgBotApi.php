<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TgBotApi implements IBotApi
{
    private string $token;

    public function __construct(string $token){
        $this->token = $token;
    }

    public function get_message(Request $request): Message|null
    {
        if(isset($request['message'])){
            if(isset($request['message']['from'])){
                $user = User::firstOrNew(['uid' => $request['message']['from']['id'], 'type' => 'tg']);
                $user->username = $request['message']['from']['first_name'];
                $user->save();

                $file_link = null;
                if(isset($request['message']['document'])){
                    $file_id = $request['message']['document']['file_id'];
                    $file_data = $this->api_call('getFile', ['file_id' => $file_id]);
                    $file_link = "https://api.telegram.org/file/bot".$this->token."/".$file_data['result']['file_path'];
                }

                return new Message('tg', $user, array_key_exists('text', $request['message']) ? $request['message']['text'] : null, file_link: $file_link);
            }
        } elseif (isset($request['callback_query'])){
            if(isset($request['callback_query']['data']) && isset($request['callback_query']['from'])){
                $user = User::firstOrNew(['uid' => $request['callback_query']['from']['id'], 'type' => 'tg']);
                $user->username = $request['callback_query']['from']['first_name'];
                $user->save();
                return new Message('tg', $user, json_decode($request['callback_query']['data'])->data, str($request['callback_query']['id']));
            }
        }
        return null;
    }

    public function send_message(User $user, string $text, array $keyboard = [], bool $is_inline_keyboard = false, ?string $callback_query_id = null): void
    {
        $compiled_keyboard = '';
        if(count($keyboard) > 0) $compiled_keyboard = $this->make_keyboard($keyboard, $is_inline_keyboard);
        $this->api_call('sendMessage', ['chat_id' => $user->uid, 'text' => $text, 'reply_markup' => $compiled_keyboard]);
        if($callback_query_id != null) $this->api_call('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
    }

    public function api_call(string $method, array $params): PromiseInterface|Response{
        return Http::post('https://api.telegram.org/bot'.$this->token.'/'.$method.'?'.http_build_query($params));
    }

    public function make_keyboard(array $buttons, bool $is_inline): string
    {
        $final_buttons = [];
        for($i = 0; $i < count($buttons); $i++){
            foreach ($buttons[$i] as $button){
                $btn = [
                    'text' => $button->getText(),
                ];
                if($button->getUrl() !== null) $btn['url'] = $button->getUrl();
                elseif($button->getCallbackData() !== '[]') $btn['callback_data'] = $button->getCallbackData();
                $final_buttons[$i][] = $btn;
            }
        }
        $keyboard = $is_inline ? ['inline_keyboard' => $final_buttons] : [
            'keyboard' => $final_buttons,
            'resize_keyboard' => true,
        ];
        return json_encode($keyboard);
    }

    public function get_type(): string
    {
        return 'tg';
    }

    public function get_user_name(User $user): string
    {
        return $user->username;
    }
}
