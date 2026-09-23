<?php

namespace App\Models;

use Database\Factories\AgentBudgetReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attempt_id', 'activity_id', 'call_number', 'local_call_key', 'reserved_nano_usd', 'actual_nano_usd', 'state', 'price_snapshot', 'request_bound', 'provider_generation_id', 'settled_at', 'retained_unknown_reason'])]
class AgentBudgetReservation extends Model
{
    /** @use HasFactory<AgentBudgetReservationFactory> */
    use HasFactory;

    public const STATE_RESERVED = 'reserved';

    public const STATE_SETTLED = 'settled';

    public const STATE_UNKNOWN = 'unknown';

    public const STATE_RELEASED = 'released';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reserved_nano_usd' => 'integer',
            'actual_nano_usd' => 'integer',
            'price_snapshot' => 'array',
            'request_bound' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PublishingAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PublishingAttempt::class, 'attempt_id');
    }

    /** @return BelongsTo<EditorialActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(EditorialActivity::class, 'activity_id');
    }
}
