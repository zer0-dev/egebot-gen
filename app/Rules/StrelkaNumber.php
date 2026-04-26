<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class StrelkaNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $response = Http::withHeaders(['Referer' => 'https://strelkacard.ru/'])->get('https://strelkacard.ru/api/cards/status/?cardnum='.$value.'&cardtypeid=3ae427a1-0f17-4524-acb1-a3f50090a8f3');
        if(!isset($response->json()['balance'])){
            $fail(__('validation.custom.card_number'));
        }
    }
}
