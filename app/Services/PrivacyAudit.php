<?php

namespace App\Services;

use App\Models\PrivacyAuditEvent;
use Illuminate\Support\Facades\Auth;

class PrivacyAudit
{
    public function record(string $action, ?string $subjectType = null, ?int $subjectId = null, array $metadata = [], ?int $userId = null): void
    {
        PrivacyAuditEvent::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata ?: null,
        ]);
    }
}
