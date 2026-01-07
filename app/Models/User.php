<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    // use HasFactory, Notifiable;

    // /**
    //  * The attributes that are mass assignable.
    //  *
    //  * @var list<string>
    //  */
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    // ];

    // /**
    //  * The attributes that should be hidden for serialization.
    //  *
    //  * @var list<string>
    //  */
    // protected $hidden = [
    //     'password',
    //     'remember_token',
    // ];
use HasApiTokens,HasFactory, Notifiable;

    protected $table = 'users';

    
    protected $fillable = [
        'name', 'email', 'phone', 'username', 
        'password', 'roles', 'avatar', 
        'created_by', 'updated_by', 'status'
    ];

    protected $hidden = [
        'password',
    ];
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    // Một user có nhiều đơn hàng
public function orders() {
    return $this->hasMany(Order::class, 'user_id');
}

// Một user gửi nhiều liên hệ
public function contacts() {
    return $this->hasMany(Contact::class, 'user_id');
}
}
