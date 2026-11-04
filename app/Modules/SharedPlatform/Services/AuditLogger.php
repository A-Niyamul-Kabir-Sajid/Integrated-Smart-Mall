<?php

namespace App\Modules\SharedPlatform\Services;

use App\Core\Middleware\AssignRequestId;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Writes business audit records. Services call record() for sensitive actions
 * (account, role, approval, price and moderation changes); diagnostic messages
 * go to the application log instead.
 */
class AuditLogger
{
    private const ACTION_PATTERN = '/^[a-z][a-z_]*(\.[a-z][a-z_]*)+$/';

    private const SECRET_KEY_PATTERN = '/pass(word)?|secret|token|credential|api[_-]?key/i';

    private const REDACTED = '[redacted]';

    public function __construct(private AuthFactory $auth, private Request $request) {}

    /**
     * @param  string  $action  Dotted code such as "shop.status_changed"
     * @param  array<string, mixed>  $metadata  Before/after values and other context; secret-looking keys are redacted
     */
    public function record(string $action, ?Model $subject = null, array $metadata = [], ?string $mallId = null, ?string $shopId = null): AuditLog
    {
        if (preg_match(self::ACTION_PATTERN, $action) !== 1) {
            throw new InvalidArgumentException("Audit action [{$action}] must look like area.event, e.g. shop.status_changed.");
        }

        $actorId = $this->auth->guard()->id();

        return AuditLog::query()->create([
            'occurred_at' => now(),
            'action' => $action,
            'actor_id' => $actorId === null ? null : (string) $actorId,
            'subject_type' => $subject?->getTable(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'mall_id' => $mallId,
            'shop_id' => $shopId,
            'request_id' => $this->request->attributes->get(AssignRequestId::ATTRIBUTE),
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 255, ''),
            'metadata' => $this->redact($metadata),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key) === 1) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
