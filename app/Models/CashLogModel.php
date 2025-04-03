<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CashLogModel extends Model
{
    use HasFactory;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // Set primary key type as string

    protected $table = 'cash_log_models';

    protected $fillable = [
        'id',
        'cash_id',
        'class_id',
        'tahun',
        'bulan',
        'type',
        'amount',
        'description',
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

    public function cash(): BelongsTo
    {
        return $this->belongsTo(CashModel::class, 'cash_id','id');
    }
}
