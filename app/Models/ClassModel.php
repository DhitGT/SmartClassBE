<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'leader_id',
        'cash_per_week'
    ];

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

    public function members()
    {
        return $this->hasMany(MemberModel::class, 'class_id');
    }
    public function teacher()
    {
        return $this->hasMany(TeacherModel::class, 'class_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'member_models', 'class_id', 'user_id');
    }
}
