<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_test_order_id',
        'patient_id',
        'technician_id',
        'parameter_name',
        'result_value',
        'unit',
        'reference_range',
        'flag',
        'notes',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public function testOrder(): BelongsTo
    {
        return $this->belongsTo(LabTestOrder::class, 'lab_test_order_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
