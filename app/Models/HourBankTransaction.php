<?php

namespace App\Models;

use Database\Factories\HourBankTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'work_date', 'minutes', 'type', 'description', 'time_adjustment_request_id', 'created_by'])]
class HourBankTransaction extends Model
{
    /** @use HasFactory<HourBankTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['work_date' => 'date', 'minutes' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustmentRequest(): BelongsTo
    {
        return $this->belongsTo(TimeAdjustmentRequest::class, 'time_adjustment_request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
