<?php

namespace App\Models;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'equipment_id', 'maintenance_plan_id', 'type', 'priority', 'status',
    'title', 'description', 'reported_by', 'assigned_to', 'scheduled_for',
    'started_at', 'completed_at', 'resolution_notes',
])]
class WorkOrder extends Model
{
    protected function casts(): array
    {
        return [
            'type' => WorkOrderType::class,
            'priority' => WorkOrderPriority::class,
            'status' => WorkOrderStatus::class,
            'scheduled_for' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<MaintenancePlan, $this>
     */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<WorkOrderLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkOrderLog::class)->latest();
    }

    public function isOverdue(): bool
    {
        return $this->scheduled_for !== null
            && $this->scheduled_for->isPast()
            && ! in_array($this->status, [WorkOrderStatus::Completada, WorkOrderStatus::Cancelada], true);
    }
}
