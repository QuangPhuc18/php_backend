<!DOCTYPE html>
<html>
<head>
    <title>Xác thực tài khoản</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Xin chào {{ $user->name }},</h2>
    <p>Cảm ơn bạn đã đăng ký tài khoản tại <strong>Coffea</strong>.</p>
    <p>Vui lòng nhấn vào nút bên dưới để kích hoạt tài khoản của bạn:</p>
    
    <p>
        <a href="{{ $url }}" style="background-color: #d97706; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Kích hoạt tài khoản
        </a>
    </p>
    
    <p>Link này sẽ hết hạn sau 60 phút.</p>
    <p>Nếu bạn không đăng ký tài khoản này, vui lòng bỏ qua email này.</p>
</body>
</html>