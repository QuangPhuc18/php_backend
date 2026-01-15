<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class VnpayService
{
    protected $vnp_TmnCode;
    protected $vnp_HashSecret;
    protected $vnp_Url;
    protected $vnp_ReturnUrl;

    public function __construct()
    {
        // ✅ SỬA:  Dùng env() thay vì config()
        $this->vnp_TmnCode = env('VNPAY_TMN_CODE', 'AXBHS2RO');
        $this->vnp_HashSecret = env('VNPAY_HASH_SECRET', 'STT6HSSKMIU05LV76G57HYK6W9D2VKSA');
        $this->vnp_Url = env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->vnp_ReturnUrl = env('VNPAY_RETURN_URL', 'http://localhost:3000/checkout/result');
    }

    public function createPayment($order)
    {
        $vnp_TxnRef = $order->id;
        $vnp_Amount = (int) $order->total_money * 100;
        $vnp_Locale = 'vn';
        $vnp_IpAddr = request()->ip() ?: '127.0.0.1';

        date_default_timezone_set('Asia/Ho_Chi_Minh');
        
        $vnp_CreateDate = date('YmdHis');
        $vnp_ExpireDate = date('YmdHis', strtotime('+15 minutes'));

        $inputData = [
            "vnp_Version"    => "2.1.0",
            "vnp_TmnCode"    => $this->vnp_TmnCode,
            "vnp_Amount"     => $vnp_Amount,
            "vnp_Command"    => "pay",
            "vnp_CreateDate" => $vnp_CreateDate,
            "vnp_CurrCode"   => "VND",
            "vnp_IpAddr"     => $vnp_IpAddr,
            "vnp_Locale"     => $vnp_Locale,
            "vnp_OrderInfo"  => "Thanh toan don hang " . $vnp_TxnRef,
            "vnp_OrderType"  => "other",
            "vnp_ReturnUrl"  => $this->vnp_ReturnUrl,
            "vnp_TxnRef"     => $vnp_TxnRef,
            "vnp_ExpireDate" => $vnp_ExpireDate,
        ];

        ksort($inputData);

        $query = "";
        $hashdata = "";
        $i = 0;

        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata.='&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $this->vnp_Url . "?" .  $query;
        $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->vnp_HashSecret);
        $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;

        Log:: info('VNPay Request:', [
            'tmnCode' => $this->vnp_TmnCode,
            'orderId' => $vnp_TxnRef,
            'amount' => $vnp_Amount,
            'createDate' => $vnp_CreateDate,
            'expireDate' => $vnp_ExpireDate,
            'returnUrl' => $this->vnp_ReturnUrl,
        ]);

        return [
            'status' => true,
            'payUrl' => $vnp_Url,
            'orderId' => $vnp_TxnRef,
        ];
    }

    public function verifyPayment($vnpData)
    {
        $vnp_SecureHash = $vnpData['vnp_SecureHash'] ?? '';
        unset($vnpData['vnp_SecureHash'], $vnpData['vnp_SecureHashType']);

        ksort($vnpData);

        $hashData = "";
        $i = 0;
        foreach ($vnpData as $key => $value) {
            if ($i == 1) {
                $hashData.= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData.= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);
        $isValid = hash_equals($secureHash, $vnp_SecureHash);

        Log::info('VNPay Verify:', [
            'isValid' => $isValid,
            'responseCode' => $vnpData['vnp_ResponseCode'] ?? 'N/A',
            'txnRef' => $vnpData['vnp_TxnRef'] ?? 'N/A',
        ]);

        return [
            'isValid' => $isValid,
            'responseCode' => $vnpData['vnp_ResponseCode'] ?? null,
            'transactionNo' => $vnpData['vnp_TransactionNo'] ?? null,
            'txnRef' => $vnpData['vnp_TxnRef'] ?? null,
            'amount' => isset($vnpData['vnp_Amount']) ? (int)$vnpData['vnp_Amount'] / 100 : 0,
            'bankCode' => $vnpData['vnp_BankCode'] ??  null,
            'payDate' => $vnpData['vnp_PayDate'] ?? null,
        ];
    }
}