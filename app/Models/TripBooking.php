<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripBooking extends Model
{
    protected $fillable = ['user_id', 'trip_id', 'boarding_stop_id', 'arrive_stop_id', 'qr_code_token', 'status', 'fare', 'booked_at', 'tapped_in_at', 'tapped_out_at'];

    protected function casts(): array
    {
        return [
            'fare' => 'decimal:2',
            'booked_at' => 'datetime',
            'tapped_in_at' => 'datetime',
            'tapped_out_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function boardingStop()
    {
        return $this->belongsTo(Halte::class, 'boarding_stop_id');
    }

    public function arriveStop()
    {
        return $this->belongsTo(Halte::class, 'arrive_stop_id');
    }

    public function rating()
    {
        return $this->hasOne(DriverRating::class);
    }
}
