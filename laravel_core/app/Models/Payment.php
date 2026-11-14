<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'guest_name',
        'guest_email',
        'amount',
        'payment_method',
        'transaction_id',
        'status',
        'paid_at',
        'receipt_url',
        'purpose',
        'amount_tendered',
        'change_amount',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
        'amount_tendered' => 'float',
        'change_amount' => 'float',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
