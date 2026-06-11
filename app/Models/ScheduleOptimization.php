<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleOptimization extends Model
{
    protected $fillable = ['schedule_id', 'generation', 'fitness_score', 'penalty_score', 'passenger_served', 'avg_wait_minutes'];

    protected function casts(): array
    {
        return [
            'fitness_score' => 'decimal:4',
            'penalty_score' => 'decimal:4',
            'avg_wait_minutes' => 'decimal:2',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
