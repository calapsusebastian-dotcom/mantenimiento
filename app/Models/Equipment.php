<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'category', 'brand', 'model', 'serial_number',
    'location', 'status', 'active', 'purchase_date', 'notes', 'created_by',
])]
class Equipment extends Model
{
    protected $attributes = [
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => EquipmentStatus::class,
            'active' => 'boolean',
            'purchase_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<MaintenancePlan, $this>
     */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
