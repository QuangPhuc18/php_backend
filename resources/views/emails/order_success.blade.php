<!DOCTYPE html>
<html>
<head><title>Đơn hàng mới</title></head>
<body>
    <h1>Cảm ơn {{ $order->name }}</h1>
    <p>Mã đơn: #{{ $order->id }}</p>
    <p>Tổng tiền: {{ number_format($order->total_money) }} đ</p>
    <p>Phương thức TT: {{ strtoupper($order->payment_method) }}</p>
    <hr>
    <h3>Chi tiết:</h3>
    <ul>
        @foreach($order->orderDetails as $detail)
            <li>
                {{ $detail->product->name ?? 'Sản phẩm' }} 
                (SL: {{ $detail->qty }}) 
                - {{ number_format($detail->price) }} đ
            </li>
        @endforeach
    </ul>
</body>
</html>