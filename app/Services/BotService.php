<?php

namespace App\Services;

use App\Models\BotButton;
use App\Models\Message;
use App\Models\Order;
use App\Models\Promocode;
use App\Models\Subject;
use App\Models\User;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BotService
{
    private IBotApi $botApi;
    private array $states;

    public function __construct(){
        $this->states = [
            fn(Message $msg) => $this->state0($msg),
            fn(Message $msg) => $this->state1($msg),
            fn(Message $msg) => $this->state2($msg),
            fn(Message $msg) => $this->state3($msg),
            fn(Message $msg) => $this->state4($msg),
            fn(Message $msg) => $this->state5($msg),
        ];
    }

    /**
     * @param IBotApi $botApi
     */
    public function setBotApi(IBotApi $botApi): void
    {
        $this->botApi = $botApi;
    }

    public function handle_message(Request $request): void
    {
        $msg = $this->botApi->get_message($request);
        if($msg === null) {
            Log::error('Сообщение null');
            Log::error('Request');
            Log::error($request);
            return;
        }
        try{
            $i = $msg->getUser()->state ?  : 0;
            $this->states[$i]($msg);
        } catch (Exception $exception){
            Log::error("!!!SERVER ERROR!!!");
            Log::error("User");
            Log::error($msg->getUser());
            Log::error("Exception");
            Log::error($exception);
            $this->sendMessageToUser($msg->getUser(), __('messages.general_error'));
        }
    }

    private function state0(Message $msg): void
    {
        $user = $msg->getUser();
        $keyboard = [];
        $is_inline = false;

        switch (true){
            case $msg->getMessage() === '/start':
            case $msg->getMessage() === 'Начать':
                $text = __('messages.shop.start_message');
                $keyboard = [
                    [
                        new BotButton(__('messages.buttons.shop.select_subjects')),
                        new BotButton(__('messages.buttons.shop.clear_cart')),
                    ],
                    [
                        new BotButton(__('messages.buttons.shop.checkout')),
                    ]
                ];
                break;
            case $msg->getMessage() === __('messages.buttons.shop.select_subjects'):
                $text = __('messages.shop.select_message');

                $keyboard = [[]];
                $is_inline = true;
                $subjects = Subject::query()->get();
                $max_in_row = 3;
                $rows = count($subjects) / $max_in_row;
                $subject_index = 0;
                for($i = 0; $i < $rows; $i++){
                    for($j = 0; $j < $max_in_row; $j++){
                        $subject = $subjects[$subject_index];
                        $keyboard[$i][] = new BotButton($subject->name, callback_data: ['data' => "selectSubject[$subject->id]"]);
                        $subject_index++;
                    }
                }

                $keyboard[] = [
                    new BotButton(__('messages.buttons.shop.clear_cart'), callback_data: ['data' => "clearCart"], required: false),
                    new BotButton(__('messages.buttons.shop.checkout'), callback_data: ['data' => "checkout"]),
                ];
                break;
            case $msg->getMessage() === 'clearCart':
            case $msg->getMessage() === __('messages.buttons.shop.clear_cart'):
                $user->subjects()->detach();
                $text = __('messages.shop.cart_cleared');
                break;
            case $msg->getMessage() === 'checkout':
            case $msg->getMessage() === __('messages.buttons.shop.checkout'):
                $cart = $user->subjects()->with(['promocodes' => function ($q) {
                    $q->where('is_sold', 0);
                }])->get();

                $allHaveFreePromocodes = $cart->every(function ($subject) {
                    return $subject->promocodes->isNotEmpty();
                });
                if(!$allHaveFreePromocodes){
                    $text = __('messages.shop.checkout_no_promocodes');
                    break;
                }
                if(count($cart) > 0){
                    $subjects_list = $cart->pluck('name')->implode(', ');
                    $total = $cart->sum('price');
                    if($cart->count() >= 3) $total -= $cart->first()->price;
                    $order = $user->orders()->create([
                        'total' => $total,
                        'status' => 'pending'
                    ]);
                    $order->subjects()->attach($cart);

                    $paymentService = app()->make(PaymentService::class);
                    $initPayment = $paymentService->init_payment($total, $subjects_list, "bot-$order->id");
                    if(!$initPayment['ok']){
                        $errorText = $initPayment['error'];
                        $text = __('messages.shop.acquiring_error', ['message' => $errorText]);
                        break;
                    }

                    $paymentId = $initPayment['payment_id'];
                    $text = __('messages.shop.checkout', ['subjects' => $subjects_list, 'order_id' => $order->id]);
                    $is_inline = true;
                    $keyboard = [
                        [
                            new BotButton(__('messages.buttons.shop.clear_cart'), callback_data: ['data' => "clearCart"]),
                            new BotButton(__('messages.buttons.shop.pay_link'), url: $initPayment['url']),
                        ],
                        [
                            new BotButton(__('messages.buttons.shop.get_promocode'), callback_data: ['data' => "getPromocode[$paymentId]"])
                        ]
                    ];
                } else {
                    $text = __('messages.shop.cart_empty');
                }
                break;
            case preg_match('/selectSubject\[(\d+)]/', $msg->getMessage(), $matches):
                $subject = Subject::query()->find($matches[1]);
                if(count($subject->promocodes()->where('is_sold', '=', '0')->get()) == 0){
                    $text = __('messages.shop.cart_no_promocodes', ['subject' => $subject->name]);
                    break;
                }
                $user->subjects()->syncWithoutDetaching($subject);

                // Add data to reminders table
                $record = DB::table('reminders')->where('user_id', '=', $user->id);
                if($record->first()){
                    $record->update(['remind_day' => now()->addDay(), 'remind_week' => now()->weekday(0)]);
                } else {
                    DB::table('reminders')
                        ->insert(['user_id' => $user->id, 'remind_day' => now()->addDay(), 'remind_week' => now()->weekday(0)]);
                }

                $text = __('messages.shop.cart_added', ['subject' => $subject->name]);
                break;
            case preg_match('/getPromocode\[(\d+)]/', $msg->getMessage(), $matches):
                $paymentService = app()->make(PaymentService::class);
                $check = $paymentService->checkPayment($matches[1]);
                if(!$check['ok']){
                    $errorText = $check['error'];
                    $text = __('messages.shop.acquiring_error', ['message' => $errorText]);
                    break;
                }

                $order = Order::query()->find($check['order_id']);
                $confStatus = Config::get('app.env') === 'production' ? 'CONFIRMED' : 'NEW';
                if($check['status'] === $confStatus && $order->status !== 'finished') $order->update(['status' => 'success']);

                if($order->status === 'pending'){
                    $text = __('messages.shop.order_pending');
                } elseif($order->status === 'success') {
                    $codes = [];
                    $subjects = $order->subjects()->get();
                    foreach ($subjects as $subject){
                        $promocode = $subject->promocodes()->where('is_sold', '=', '0')->inRandomOrder()->first();
                        if($promocode !== null){
                            $codes[] = $promocode->code;
                            $promocode->update(['is_sold' => 1]);
                        }
                        if($promocode === null) $codes[] = __('messages.shop.cart_no_promocodes', ['subject' => $subject->name]);
                    }
                    $this->botApi->send_message($user, __('messages.shop.order_success'));

                    foreach ($codes as $code){
                        $this->botApi->send_message($user, $code);
                    }
                    $this->botApi->send_message($user, __('messages.shop.goodluck'), $keyboard, $is_inline, $msg->getCallbackQueryId());


                    $order->update(['status' => 'finished']);
                    $user->subjects()->detach();

                    $subjectsWithLittleStock = $this->checkPromocodesStock();
                    if(count($subjectsWithLittleStock) > 0){
                        $this->sendMessageToAdmin(__('messages.admin.restock_needed', ['subjects' => implode("\n", $subjectsWithLittleStock)]));
                    }
                    return;
                } elseif ($order->status === 'finished'){
                    $text = __('messages.shop.order_finished');
                }
                break;
            case $msg->getMessage() === '/admin':
                if($user->type == Config::get('services.admin.type') && $user->uid == Config::get('services.admin.id')){
                    $text = __('messages.admin.default_message');
                    $keyboard = [
                        [new BotButton(__('messages.buttons.admin.everyone'))],
                        [new BotButton(__('messages.buttons.admin.upload_promocodes'))],
                        [new BotButton(__('messages.buttons.admin.change_price'))],
                        [new BotButton(__('messages.buttons.admin.reminder')), new BotButton(__('messages.cancel'))],
                    ];
                    $user->update(['state' => 1]);
                    break;
                }
            default:
                $text = __('messages.shop.default_message');
                $keyboard = [
                    [
                        new BotButton(__('messages.buttons.shop.select_subjects')),
                        new BotButton(__('messages.buttons.shop.clear_cart')),
                    ],
                    [
                        new BotButton(__('messages.buttons.shop.checkout')),
                    ]
                ];
                break;
        }
        $this->botApi->send_message($user, $text, $keyboard, $is_inline, $msg->getCallbackQueryId());
    }

    private function state1(Message $msg)
    {
        $user = $msg->getUser();
        $keyboard = [];
        $is_inline = false;
        if($user->uid != Config::get('services.admin.id')){
            $user->update(['state' => 0]);
            $this->state0($msg);
            return;
        }

        switch ($msg->getMessage()){
            case __('messages.buttons.admin.everyone'):
                $text = __('messages.admin.everyone');
                $user->update(['state' => 2]);
                break;
            case __('messages.buttons.admin.upload_promocodes'):
                $text = __('messages.admin.upload_promocodes_select');
                $subjects = Subject::query()->get();
                $max_in_row = 3;
                $rows = count($subjects) / $max_in_row;
                $subject_index = 0;
                $is_inline = true;
                for($i = 0; $i < $rows; $i++){
                    for($j = 0; $j < $max_in_row; $j++){
                        $subject = $subjects[$subject_index];
                        $keyboard[$i][] = new BotButton($subject->name, callback_data: ['data' => "selectSubjectUpload[$subject->id]"]);
                        $subject_index++;
                    }
                }
                $user->update(['state' => 3]);
                break;
            case __('messages.buttons.admin.change_price'):
                $text = __('messages.admin.change_price');
                $user->update(['state' => 4]);
                break;
            case __('messages.buttons.admin.reminder'):
                $text = __('messages.admin.reminder');
                $user->update(['state' => 5]);
                break;
            case __('messages.cancel'):
                $text = __('messages.admin.exit');
                $user->update(['state' => 0]);
                $this->state0($msg);
                break;
            default:
                $text = __('messages.admin.default_message');
                break;
        }

        $this->botApi->send_message($user, $text, $keyboard, $is_inline, $msg->getCallbackQueryId());
    }

    private function state2(Message $msg)
    {
        $user = $msg->getUser();
        $keyboard = [];
        $is_inline = false;
        if($user->uid != Config::get('services.admin.id')){
            $user->update(['state' => 0]);
            $this->state0($msg);
            return;
        }

        if($msg->getMessage() === __('messages.cancel')){
            $user->update(['state' => 1]);
            $this->botApi->send_message($user, __('messages.admin.default_message'), $keyboard, false, $msg->getCallbackQueryId());
            return;
        }

        $text = __('messages.admin.everyone_success');

        $this->botApi->send_message($user, $text, $keyboard, $is_inline, $msg->getCallbackQueryId());
        $user->update(['state' => 1]);
        $this->sendEveryone($msg->getMessage());
    }

    private function state3(Message $msg)
    {
        $user = $msg->getUser();
        $keyboard = [];
        $is_inline = true;

        if($user->uid != Config::get('services.admin.id')){
            $user->update(['state' => 0]);
            $this->state0($msg);
            return;
        }

        if(preg_match('/selectSubjectUpload\[(\d+)]/', $msg->getMessage(), $matches)){
            $subject = Subject::query()->find($matches[1]);
            $text = __('messages.admin.upload_promocodes', ['subject' => $subject->name]);
            $this->generatePromocodes(intval($matches[1]));
        } else {
            $user->update(['state' => 1]);
            $text = __('messages.admin.default_message');
        }

        $this->botApi->send_message($user, $text, $keyboard, $is_inline, $msg->getCallbackQueryId());
    }

    private function state4(Message $msg)
    {
        $user = $msg->getUser();
        $keyboard = [];
        $is_inline = false;
        if($user->uid != Config::get('services.admin.id')){
            $user->update(['state' => 0]);
            $this->state0($msg);
            return;
        }

        if($msg->getMessage() === __('messages.cancel')){
            $user->update(['state' => 1]);
            $this->botApi->send_message($user, __('messages.admin.default_message'), $keyboard, false, $msg->getCallbackQueryId());
            return;
        }

        $newPrice = $msg->getMessage();
        if(ctype_digit($newPrice)){
            Subject::query()->update(['price' => intval($newPrice)]);
            $text = __('messages.admin.change_price_success', ['price' => $newPrice]);
            $user->update(['state' => 1]);
        } else {
            $text = __('messages.admin.change_price_fail');
        }

        $this->botApi->send_message($user, $text, $keyboard, $is_inline, $msg->getCallbackQueryId());
    }

    private function state5(Message $msg)
    {
        $user = $msg->getUser();
        $keyboard = [];
        $is_inline = false;
        if($user->uid != Config::get('services.admin.id')){
            $user->update(['state' => 0]);
            $this->state0($msg);
            return;
        }

        if($msg->getMessage() === __('messages.cancel')){
            $user->update(['state' => 1]);
            $this->botApi->send_message($user, __('messages.admin.default_message'), $keyboard, false, $msg->getCallbackQueryId());
            return;
        }

        $text = __('messages.admin.reminder_success');

        $this->botApi->send_message($user, $text, $keyboard, $is_inline, $msg->getCallbackQueryId());
        $user->update(['state' => 1]);
        $this->sendReminder($msg->getMessage());
    }

    private function sendEveryone(string $message){
        $users = User::query()->get();
        foreach ($users as $user){
            $this->sendMessageToUser($user, $message);
        }
    }

    private function generatePromocodes(int $subject_id){
        $codes = [];
        for($i = 0; $i < 50; $i++){
            $codes[] = $this->generate_promo($subject_id);
        }

        $data = [];
        $now = now();

        foreach ($codes as $code) {
            $code = trim($code);

            if ($code === '') continue;

            $data[] = [
                'code' => $code,
                'subject_id' => $subject_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Promocode::insert($data);
        $this->sendMessageToAdmin(__('messages.admin.upload_promocodes_success', ['amount' => count($codes)]));
    }

    private function sendReminder(string $message){
        $users = User::query()->whereHas('subjects')->get();
        foreach ($users as $user){
            $this->sendMessageToUser($user, $message);
        }
    }

    private function checkPromocodesStock(){
        $subjects = DB::table('subjects')
            ->leftJoin('promocodes', function ($join) {
                $join->on('subjects.id', '=', 'promocodes.subject_id')
                    ->where('promocodes.is_sold', 0);
            })
            ->groupBy('subjects.id', 'subjects.name')
            ->havingRaw('COUNT(promocodes.id) < 5')
            ->get([
                'subjects.name',
            ]);
        return $subjects->map(function ($e) {
            return $e->name;
        })->toArray();
    }

    public function sendMessageToUser(User $user, string $message)
    {
        $botApi = null;
        if($user->type === 'tg') $botApi = app(TgBotApi::class);
        if($user->type === 'vk') $botApi = app(VkBotApi::class);
        if($user->type === 'max') $botApi = app(MaxBotApi::class);
        if($botApi !== null){
            $botApi->send_message($user, $message);
        }
    }

    private function sendMessageToAdmin(string $message){
        $admin_user = User::query()->where('uid', '=', Config::get('services.admin.id'))->where('type', '=', Config::get('services.admin.type'))->get()[0];
        $this->sendMessageToUser($admin_user, $message);
    }

    private function generate_promo(int $subject_id) {
        $keys_for_items = [
            [
                "month_position" => -1,
                "set_letter" => []
            ],
            [
                "month_position" => 0,
                "set_letter" => ['r','u','s']
            ],
            [
                "month_position" => 1,
                "set_letter" => ['m','a','t']
            ],
            [
                "month_position" => 2,
                "set_letter" => ['o','b','s']
            ],
            [
                "month_position" => 1,
                "set_letter" => ['r','u','s']
            ],
            [
                "month_position" => 2,
                "set_letter" => ['c','h','e']
            ],
            [
                "month_position" => 1,
                "set_letter" => ['b','i','o']
            ],
            [
                "month_position" => 0,
                "set_letter" => ['l','i','t']
            ],
            [
                "month_position" => 2,
                "set_letter" => ['s','b','o']
            ],
            [
                "month_position" => 0,
                "set_letter" => ['e','n','g']
            ]
        ];
        // текущая дата
        $now = new DateTime("now", new DateTimeZone(date_default_timezone_get()));

        // месяц (аналог %B.lower())
        $month = strtolower($now->format('F'));
        $month_letter = $month[$keys_for_items[$subject_id]["month_position"]];

        // день % 10
        $day_div = intval($now->format('d')) % 10;

        $letters = range('a', 'z');
        $digits = range('0', '9');

        // случайная буква из набора
        $set_letter = $keys_for_items[$subject_id]["set_letter"][array_rand($keys_for_items[$subject_id]["set_letter"])];

        // 2 случайные буквы
        $other_letter = '';
        for ($i = 0; $i < 2; $i++) {
            $other_letter .= $letters[array_rand($letters)];
        }

        // 3 случайные цифры
        $other_digits = '';
        for ($i = 0; $i < 3; $i++) {
            $other_digits .= $digits[array_rand($digits)];
        }

        $promo = $month_letter . $day_div . $other_letter . $other_digits . $set_letter;

        // перемешивание строки (аналог random.sample)
        $promo_array = str_split($promo);
        shuffle($promo_array);

        return implode('', $promo_array);
    }
}
