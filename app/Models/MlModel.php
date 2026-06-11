<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MlModel extends Model
{
    protected $fillable = ['version', 'algorithm', 'accuracy', 'mae', 'rmse', 'hyperparameters', 'model_file_path', 'training_rows', 'is_active', 'trained_at'];

    protected function casts(): array
    {
        return [
            'accuracy' => 'decimal:4',
            'mae' => 'decimal:4',
            'rmse' => 'decimal:4',
            'hyperparameters' => 'array',
            'is_active' => 'boolean',
            'trained_at' => 'datetime',
        ];
    }

    public function predictions()
    {
        return $this->hasMany(PassengerPrediction::class);
    }
}
