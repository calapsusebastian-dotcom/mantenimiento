<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'equipment_id', 'name', 'checklist', 'frequency_days',
    'next_due_date', 'active', 'created_by',
])]
class MaintenancePlan extends Model
{
    protected function casts(): array
    {
        return [
            'next_due_date' => 'date',
            'active' => 'boolean',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function isDue(): bool
    {
        return $this->active && $this->next_due_date->isPast();
    }
}
