<?php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PersonalAccessToken extends Model
{
    // public $incrementing = false; // Disable auto-increment
    // protected $keyType = 'string'; // Set primary key type as string


    // protected $table = 'personal_access_tokens';
    // protected $fillable = ['id'];

    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($model) {
    //         if (empty($model->id)) {
    //             $model->id = Str::uuid(); // Generate UUID
    //         }
    //     });
    // }
}
