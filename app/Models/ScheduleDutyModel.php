<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleDutyModel extends Model
{
    use HasFactory;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // Set primary key type as string

    protected $fillable = ['day','member_id','class_id'];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid(); // Generate UUID
            }
        });
    }

    public function member()
    {
        return $this->belongsTo(MemberModel::class,'member_id');
    }
}
