<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HmsAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'target_role',
        'alert_type',
        'title',
        'message',
        'priority',
        'is_read',
        'link',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
