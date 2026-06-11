<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassengerHistory extends Model
{
    protected $fillable = ['route_id', 'csv_upload_id', 'record_date', 'hour', 'day_type', 'passenger_count'];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
        ];
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function csvUpload()
    {
        return $this->belongsTo(CsvUpload::class);
    }
}
