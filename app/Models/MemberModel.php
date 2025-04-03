<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MemberModel extends Model
{
    use HasFactory;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // Set primary key type as string

    protected $fillable = [
        'user_id',
        'class_id',
        'access_code',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kas(): HasMany
    {
        return $this->hasMany(CashModel::class, 'member_id');
    }

    // Relationship: Each Member belongs to one Class
    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }
}
