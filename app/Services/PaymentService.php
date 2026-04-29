<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private  $apiUrl = 'https://securepay.tinkoff.ru/v2';

    public function init_payment(int $price, string $description, string $orderId){
        $data = [
            'TerminalKey' => Config::get('services.payment.terminal_key'),
            'Amount' => $price,
            'Description' => $description,
            'OrderId' => $orderId,
        ];
        $token = $this->get_token($data);
        $data['Token'] = $token;
        $init = Http::post($this->apiUrl.'/Init', $data);
        $result = $init->json();
        if($result['Success']){
            return ['ok' => true, 'url' => $result['PaymentURL'], 'payment_id' => $result['PaymentId']];
        } else{
            return ['ok' => false, 'error' => $result['Message']];
        }
    }

    public function checkPayment(string $paymentId){
        $data = [
            'TerminalKey' => Config::get('services.payment.terminal_key'),
            'PaymentId' => $paymentId
        ];
        $token = $this->get_token($data);
        $data['Token'] = $token;
        $check = Http::post($this->apiUrl.'/GetState', $data);
        $result = $check->json();
        if($result['Success']){
            $orderId = explode('-', $result['OrderId']);
            if($result['TerminalKey'] === Config::get('services.payment.terminal_key') && $orderId[0] === 'bot3'){
                return ['ok' => true, 'status' => $result['Status'], 'order_id' => $orderId[1]];
            } else {
                return ['ok' => false, 'error' => 'Wrong parameters for GetState'];
            }
        } else{
            return ['ok' => false, 'error' => $result['Message']];
        }
    }

    private function get_token($params){
        $params = $this->normalizeArray($params);
        $params['Password'] = Config::get('services.payment.password');
        ksort($params);
        $all = implode('', $params);
        if (!mb_check_encoding($all, 'UTF-8')) {
            $all = mb_convert_encoding($all, 'UTF-8');
        }
        return hash('sha256', $all);
    }

    private function normalizeArray($array) {
        foreach ($array as &$el) {
            if (is_bool($el)) {
                $el = ($el) ? "true" : "false";
            } elseif (is_array($el)) {
                $el = $this->normalizeArray($el);
            }
        }
        return $array;
    }
}
