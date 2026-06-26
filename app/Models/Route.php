<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $fillable = ['code', 'name', 'total_distance_km', 'time_start', 'time_end', 'avg_speed', 'max_freq_per_hour', 'is_active', 'fare_per_km'];

    protected function casts(): array
    {
        return [
            'total_distance_km' => 'decimal:2',
            'avg_speed' => 'decimal:2',
            'is_active' => 'boolean',
            'fare_per_km' => 'decimal:2',
        ];
    }

    public function haltes()
    {
        return $this->belongsToMany(Halte::class, 'route_haltes')
                    ->withPivot('sequence', 'distance_from_prev_halte')
                    ->withTimestamps();
    }

    public function routeHaltes()
    {
        return $this->hasMany(RouteHalte::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function passengerHistories()
    {
        return $this->hasMany(PassengerHistory::class);
    }

    public function passengerPredictions()
    {
        return $this->hasMany(PassengerPrediction::class);
    }

    public function favouritedBy()
    {
        return $this->hasMany(FavouriteRoute::class);
    }

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }
}
