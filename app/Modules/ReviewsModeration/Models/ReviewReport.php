<?php

namespace App\Modules\ReviewsModeration\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ReviewsModeration\Enums\ReportReason;
use App\Modules\ReviewsModeration\Enums\ReportStatus;
use Database\Factories\ReviewReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * REVIEW_REPORT: one user's report about one review. The reporter id is hidden from
 * serialisation so it cannot leak into a page or response by accident.
 */
#[Table(name: 'review_reports', key: 'review_report_id')]
#[Fillable(['review_id', 'reporter_user_id', 'reason_code', 'details', 'status', 'resolved_at'])]
#[Hidden(['reporter_user_id'])]
#[UseFactory(ReviewReportFactory::class)]
class ReviewReport extends Model
{
    /** @use HasFactory<ReviewReportFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (ReviewReport $report): void {
            foreach (['review_id', 'reporter_user_id', 'reason_code', 'details'] as $attribute) {
                if ($report->isDirty($attribute)) {
                    throw new LogicException("A report's {$attribute} never changes.");
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Reports are resolved or dismissed, never deleted.');
        });
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'review_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Open->value);
    }

    protected function casts(): array
    {
        return [
            'reason_code' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
