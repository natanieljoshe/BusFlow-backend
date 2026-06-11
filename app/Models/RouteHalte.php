<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RouteHalte extends Pivot
{
    protected $table = 'route_haltes';
    public $incrementing = true;

    protected $fillable = ['route_id', 'halte_id', 'sequence', 'distance_from_prev_halte'];

    protected function casts(): array
    {
        return [
            'distance_from_prev_halte' => 'decimal:2',
        ];
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function halte()
    {
        return $this->belongsTo(Halte::class);
    }
}
