<?php

namespace App\Modules\SharedPlatform\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One immutable business audit record. Created only through AuditLogger;
 * updating or deleting a record throws.
 */
#[Table(name: 'audit_logs', key: 'audit_log_id', timestamps: false)]
#[Fillable([
    'occurred_at', 'action', 'actor_id', 'subject_type', 'subject_id', 'mall_id', 'shop_id',
    'request_id', 'ip_address', 'user_agent', 'metadata',
])]
class AuditLog extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit records are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Audit records are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
