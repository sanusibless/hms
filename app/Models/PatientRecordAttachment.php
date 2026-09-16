<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientRecordAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'health_record_id',
        'title',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function healthRecord(): BelongsTo
    {
        return $this->belongsTo(PatientHealthRecord::class, 'health_record_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
