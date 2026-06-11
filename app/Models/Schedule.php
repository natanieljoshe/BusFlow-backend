<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = ['valid_from', 'valid_until', 'is_active', 'fitness_score', 'total_penalty', 'avg_wait_min', 'passenger_served'];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
            'fitness_score' => 'decimal:4',
            'total_penalty' => 'decimal:4',
            'avg_wait_min' => 'decimal:2',
        ];
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function gaParameter()
    {
        return $this->hasOne(GaParameter::class);
    }

    public function optimizations()
    {
        return $this->hasMany(ScheduleOptimization::class);
    }

    public function exports()
    {
        return $this->hasMany(ScheduleExport::class);
    }
}
