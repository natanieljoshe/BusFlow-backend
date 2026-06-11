<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleExport extends Model
{
    protected $fillable = ['schedule_id', 'exported_by', 'file_path'];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function exportedBy()
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
