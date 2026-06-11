<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Halte extends Model
{
    protected $fillable = ['name', 'code', 'latitude', 'longitude', 'address', 'is_active'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function routes()
    {
        return $this->belongsToMany(Route::class, 'route_haltes')
                    ->withPivot('sequence', 'distance_from_prev_halte')
                    ->withTimestamps();
    }

    public function boardingTrips()
    {
        return $this->hasMany(TripBooking::class, 'boarding_stop_id');
    }

    public function arriveTrips()
    {
        return $this->hasMany(TripBooking::class, 'arrive_stop_id');
    }
}
