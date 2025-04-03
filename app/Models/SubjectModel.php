<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubjectModel extends Model
{
    use HasFactory;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // Set primary key type as string


    protected $fillable = ['class_id', 'name', 'description', 'icon'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid(); // Generate UUID
            }
        });
    }

    public function teacher()
    {
        return $this->hasOne(TeacherModel::class, 'subject_id', 'id');
    }

    public function task()
    {
        return $this->hasOne(TaskSubjectModel::class, 'subject_id', 'id');
    }
}
