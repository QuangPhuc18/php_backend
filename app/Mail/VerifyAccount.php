<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyAccount extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $url;

    // Nhận User và Link xác thực từ Controller
    public function __construct(User $user, $url)
    {
        $this->user = $user;
        $this->url = $url;
    }

    public function build()
    {
        return $this->subject('Kích hoạt tài khoản Coffea')
                    ->view('emails.verify_account'); // Trỏ đến file view
    }
}