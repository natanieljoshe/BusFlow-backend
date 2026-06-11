<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusMaintenanceLog extends Model
{
    protected $fillable = ['bus_id', 'scheduled_at', 'completed_at', 'km_at_service'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'km_at_service' => 'decimal:2',
        ];
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }
}
