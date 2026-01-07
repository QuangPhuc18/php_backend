<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $table = 'contacts';

    protected $fillable = [
        'user_id', 'name', 'email', 'phone', 
        'content', 'reply_id', 
        'created_by', 'updated_by', 'status'
    ];
    public function user() {
    return $this->belongsTo(User::class, 'user_id');
}

// Trả lời cho liên hệ nào (quan hệ đệ quy)
public function parentContact() {
    return $this->belongsTo(Contact::class, 'reply_id');
}

// Các câu trả lời của liên hệ này
public function replies() {
    return $this->hasMany(Contact::class, 'reply_id');
}
}