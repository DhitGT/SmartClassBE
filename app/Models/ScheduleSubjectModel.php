<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleSubjectModel extends Model
{
    use HasFactory;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // Set primary key type as string

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid(); // Generate UUID
            }
        });
    }
}
