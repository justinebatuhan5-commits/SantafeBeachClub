<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';
    public $timestamps = false; // Uses custom created_at timestamp and specific datetime columns

    protected $fillable = [
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_country',
        'guest_type',
        'check_in',
        'check_out',
        'guests_count',
        'extra_adults',
        'extra_children',
        'room_id',
        'room_type_id',
        'accommodation_name',
        'eta',
        'status',
        'checked_in_at',
        'checked_out_at',
        'checkin_token',
        'payment_method',
        'cancellation_token',
        'cancelled_at',
        'guest_special_requests',
        'guest_notes',
        'payment_deadline',
        'payment_status',
        'promo_code',
        'discount_amount',
        'prearrival_reminder_sent_at',
        'checkout_notified_at',
        'balance_status',
        'balance_due_at',
        'balance_paid_at',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'guests_count' => 'integer',
        'extra_adults' => 'integer',
        'extra_children' => 'integer',
        'discount_amount' => 'float',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'prearrival_reminder_sent_at' => 'datetime',
        'checkout_notified_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'booking_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['Cancelled', 'Cancelled / Refunded', 'Checked Out']);
    }

    public function scopeUpcomingCheckins($query, $date = null)
    {
        $targetDate = $date ?? now()->toDateString();
        return $query->where('check_in', $targetDate)
                     ->whereIn('status', ['Confirmed', 'Pending Payment', 'Pending']);
    }

    public function scopePendingCheckouts($query, $date = null)
    {
        $targetDate = $date ?? now()->toDateString();
        return $query->where('check_out', $targetDate)
                     ->where('status', 'Checked In');
    }
}
