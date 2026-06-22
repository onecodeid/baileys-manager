<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone_number', 'status', 'qr_code', 'session_data', 'last_active'
    ];

    protected $casts = [
        'session_data' => 'array'
    ];
}
