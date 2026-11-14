<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    protected $table = 'room_types';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'total_rooms',
        'price',
        'image_url',
        'gallery_images',
    ];

    protected $casts = [
        'price' => 'float',
        'total_rooms' => 'integer',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class, 'type', 'name');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'room_type_id');
    }
}
