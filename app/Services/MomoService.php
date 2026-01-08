<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MomoService
{
    // Thông tin cấu hình MoMo Sandbox (Dùng chung cho developer)
    protected $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
    protected $partnerCode = "MOMOBKUN20180529";
    protected $accessKey = "klm05TvNBzhg7h7j";
    protected $secretKey = "at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa";

    public function createPayment($order)
    {
        $requestId = (string) time();
        $orderId = (string) $order->id . '_' . time(); // ID đơn hàng phải duy nhất từng lần gọi
        $amount = (string) $order->total_money;
        
        // Link frontend nhận kết quả sau khi thanh toán
        // Giả sử frontend chạy port 3000
        $redirectUrl = "http://localhost:3000/checkout/result"; 
        
        // Link backend nhận thông báo ngầm (IPN) - Cần public domain hoặc ngrok để test
        $ipnUrl = "http://localhost:8000/api/momo/ipn"; 
        
        $orderInfo = "Thanh toan don hang #" . $order->id;
        $extraData = "";

        // Tạo chữ ký (Signature) theo chuẩn MoMo
        $rawHash = "accessKey=" . $this->accessKey . 
                   "&amount=" . $amount . 
                   "&extraData=" . $extraData . 
                   "&ipnUrl=" . $ipnUrl . 
                   "&orderId=" . $orderId . 
                   "&orderInfo=" . $orderInfo . 
                   "&partnerCode=" . $this->partnerCode . 
                   "&redirectUrl=" . $redirectUrl . 
                   "&requestId=" . $requestId . 
                   "&requestType=captureWallet";

        $signature = hash_hmac("sha256", $rawHash, $this->secretKey);

        $data = [
            'partnerCode' => $this->partnerCode,
            'partnerName' => "Test Momo",
            'storeId' => "MomoTestStore",
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => 'captureWallet',
            'signature' => $signature
        ];

        // Gọi API MoMo
        $response = Http::post($this->endpoint, $data);
        
        return $response->json();
    }
}