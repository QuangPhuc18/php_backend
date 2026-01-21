<!DOCTYPE html>
<html>
<head><title>Đơn hàng mới</title></head>
<body>
    <h1>Cảm ơn {{ $order->name }}</h1>
<p>Tổng tiền: <strong>{{ number_format($order->total_money, 0, ',', '.') }} đ</strong></p>

<p>Phương thức TT: 
    @if($order->payment_method == 'vnpay')
        <span style="color: blue; font-weight: bold;">Thanh toán online qua VNPAY</span>
    @else
        <span style="color: green; font-weight: bold;">Tiền mặt (COD)</span>
    @endif
</p>    <hr>
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