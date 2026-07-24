<?php

namespace App\Models;

use App\Enums\CheckoutCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'equipment_id', 'taken_by', 'destination', 'condition_out', 'checked_out_by', 'checked_out_at',
    'condition_in', 'returned_by', 'returned_at', 'notes',
])]
class EquipmentCheckout extends Model
{
    protected function casts(): array
    {
        return [
            'condition_out' => CheckoutCondition::class,
            'condition_in' => CheckoutCondition::class,
            'checked_out_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function isOut(): bool
    {
        return $this->returned_at === null;
    }
}
