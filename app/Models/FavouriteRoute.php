<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FavouriteRoute extends Model
{
    protected $fillable = ['user_id', 'route_id', 'halte_id', 'label'];

    public function user()
    {
        return $this->belongsTo(User::class);
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
