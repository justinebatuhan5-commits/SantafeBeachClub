<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Administrator extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'administrators';
    public $timestamps = false; // table only has created_at without updated_at

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
        return 'admin';
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }
}
