<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';
    public $timestamps = false;

    protected $fillable = [
        'room_number',
        'name',
        'type',
        'price_per_night',
        'capacity',
        'status',
    ];

    protected $casts = [
        'price_per_night' => 'float',
        'capacity' => 'integer',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class, 'type', 'name');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'room_id');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeAvailableForBooking($query)
    {
        return $query->where('status', '<>', 'maintenance');
    }
}
