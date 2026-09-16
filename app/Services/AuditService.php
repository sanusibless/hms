<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public static function log(
        string $action,
        string $module,
        ?string $recordId = null,
        string $description = '',
        ?User $user = null
    ): AuditLog {
        $currentUser = $user ?? Auth::user();

        return AuditLog::create([
            'user_id' => $currentUser?->id,
            'user_name' => $currentUser?->name ?? 'System',
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => Request::ip() ?? '127.0.0.1',
        ]);
    }
}
