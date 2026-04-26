<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BotService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PingDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ping-day';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Пингует всех юзеров с непустой корзиной, заполненной в вчера';

    /**
     * Execute the console command.
     */
    public function handle(BotService $botService)
    {
        $start = Carbon::now()->startOfMinute();
        $end = Carbon::now()->endOfMinute();
        $users = User::query()
            ->join('reminders', 'reminders.user_id', '=', 'users.id')
            ->whereBetween('reminders.remind_day', [$start, $end])
            ->select('users.*')
            ->distinct()
            ->get();
        foreach ($users as $user){
            $botService->sendMessageToUser( $user, __('messages.reminders.day'));
            $user->update(['notified_at' => now()]);
        }
    }
}
