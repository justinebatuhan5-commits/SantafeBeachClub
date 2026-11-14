<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Receptionist extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'receptionists';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'email',
        'profile_photo',
        'failed_login_count',
        'locked_until',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'locked_until' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    public function getRoleAttribute(): string
    {
        return 'receptionist';
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }
}
