<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conductor extends Model
{
    protected $fillable = ['user_id', 'employee_id', 'phone', 'is_available', 'joined_at'];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'joined_at' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }
}
