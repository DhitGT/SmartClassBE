<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CashModel extends Model
{
    
    use HasFactory;
    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // Set primary key type as string

    protected $fillable = ['id','member_id', 'tahun', 'bulan', 'minggu', 'status', 'nominal', 'tanggal'];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'siswa_id');
    }
    
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }
    public function cashLog(): BelongsTo
    {
        return $this->belongsTo(CashLogModel::class, 'cash_id');
    }

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
