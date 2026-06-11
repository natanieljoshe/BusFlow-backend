<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassengerPrediction extends Model
{
    protected $fillable = ['route_id', 'ml_model_id', 'prediction_date', 'hour', 'day_type', 'predicted_count', 'confidence_lower', 'confidence_upper'];

    protected function casts(): array
    {
        return [
            'prediction_date' => 'date',
            'confidence_lower' => 'decimal:2',
            'confidence_upper' => 'decimal:2',
        ];
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function mlModel()
    {
        return $this->belongsTo(MlModel::class);
    }
}
