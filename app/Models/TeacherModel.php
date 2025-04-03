<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TeacherModel extends Model
{
    use HasFactory;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // Set primary key type as string


    protected $fillable = [
        'name',
        'subject_id',
        'class_id',
        'avatar'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid(); // Generate UUID
            }
        });
    }

    public function classes()
    {
        return $this->belongsTo(ClassModel::class, 'class_models', 'teacher_id', 'class_id');
    }
    public function subject()
    {
        return $this->belongsTo(SubjectModel::class, 'subject_id');
    }
}
