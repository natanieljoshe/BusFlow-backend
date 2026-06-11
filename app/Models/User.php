<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    public function conductor()
    {
        return $this->hasOne(Conductor::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function tripBookings()
    {
        return $this->hasMany(TripBooking::class);
    }

    public function driverRatings()
    {
        return $this->hasMany(DriverRating::class);
    }

    public function favouriteRoutes()
    {
        return $this->hasMany(FavouriteRoute::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function csvUploads()
    {
        return $this->hasMany(CsvUpload::class, 'uploaded_by');
    }

    public function scheduleExports()
    {
        return $this->hasMany(ScheduleExport::class, 'exported_by');
    }
}
