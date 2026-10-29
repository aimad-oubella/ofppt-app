<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Audit
{
    public static function log(string $action, ?Model $subject = null, array $old = [], array $new = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
