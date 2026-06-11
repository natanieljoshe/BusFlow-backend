<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    protected $fillable = ['plate_number', 'capacity', 'year', 'brand', 'status', 'total_distance'];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'total_distance' => 'decimal:2',
        ];
    }

    public function maintenanceLogs()
    {
        return $this->hasMany(BusMaintenanceLog::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }
}
