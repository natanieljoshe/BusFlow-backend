<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GaParameter extends Model
{
    protected $fillable = ['schedule_id', 'population_size', 'generations', 'crossover_rate', 'mutation_rate', 'selection_method', 'elitism_count', 'min_driver_rest_min'];

    protected function casts(): array
    {
        return [
            'crossover_rate' => 'decimal:2',
            'mutation_rate' => 'decimal:2',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
