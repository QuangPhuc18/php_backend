<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MomoService
{
    protected $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
    protected $partnerCode = "MOMOBKUN20180529";
    protected $accessKey = "klm05TvNBzhg7h7j";
    protected $secretKey = "at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa";

    public function createPayment($order)
    {
        $requestId = (string) time();
        $orderId = (string) $order->id;
        
        if (is_numeric($order->id)) {
            $orderId = $order->id .  '_' . time();
        }

        $amount = (string) (int) $order->total_money;
        
        // URL người dùng quay về sau thanh toán
        $redirectUrl = "http://localhost:3000/checkout/result/"; 
        
        // URL backend nhận callback (PHẢI DÙNG NGROK)
        $ipnUrl = "https://bairnish-maurice-panickingly.ngrok-free.dev/api/orders/ipn"; 
        
        $orderInfo = "Thanh toan don hang " . $orderId;
        $extraData = ""; 

        // ✅ THAY ĐỔI QUAN TRỌNG:  payWithMethod cho phép nhập thẻ ATM/Credit
        $requestType = "payWithMethod"; 

        // Tạo chữ ký
        $rawHash = "accessKey=" . $this->accessKey . 
                   "&amount=" . $amount . 
                   "&extraData=" . $extraData . 
                   "&ipnUrl=" . $ipnUrl . 
                   "&orderId=" . $orderId . 
                   "&orderInfo=" .  $orderInfo . 
                   "&partnerCode=" . $this->partnerCode .  
                   "&redirectUrl=" . $redirectUrl . 
                   "&requestId=" .  $requestId . 
                   "&requestType=" . $requestType;

        $signature = hash_hmac("sha256", $rawHash, $this->secretKey);

        $data = [
            'partnerCode' => $this->partnerCode,
            'partnerName' => "Test",
            'storeId' => "MomoTestStore",
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType, // payWithMethod
            'signature' => $signature
        ];

        try {
            Log::info('MoMo Request:', $data);

            $response = Http::timeout(30)
                            ->withOptions(['verify' => false])
                            ->post($this->endpoint, $data);
            
            $jsonResult = $response->json();

            Log::info('MoMo Response:', $jsonResult);

            if (! isset($jsonResult['payUrl'])) {
                Log:: error('MoMo Error - No payUrl:', $jsonResult);
            }

            return $jsonResult;

        } catch (\Exception $e) {
            Log:: error('MoMo Exception:  ' . $e->getMessage());
            return ['message' => $e->getMessage()];
        }
    }
}