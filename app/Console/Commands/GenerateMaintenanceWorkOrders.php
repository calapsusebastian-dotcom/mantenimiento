<?php

namespace App\Console\Commands;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\MaintenancePlan;
use App\Models\WorkOrder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('maintenance:generate-work-orders')]
#[Description('Genera órdenes de trabajo preventivas para los planes de mantenimiento vencidos')]
class GenerateMaintenanceWorkOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $created = 0;

        MaintenancePlan::query()
            ->where('active', true)
            ->where('next_due_date', '<=', $today)
            ->with('equipment')
            ->each(function (MaintenancePlan $plan) use ($today, &$created) {
                WorkOrder::create([
                    'equipment_id' => $plan->equipment_id,
                    'maintenance_plan_id' => $plan->id,
                    'type' => WorkOrderType::Preventivo,
                    'priority' => WorkOrderPriority::Media,
                    'status' => WorkOrderStatus::Pendiente,
                    'title' => 'Mantenimiento preventivo: '.$plan->name,
                    'description' => $plan->checklist,
                    'scheduled_for' => $plan->next_due_date,
                ]);

                $created++;

                // Avanza la próxima fecha de vencimiento hasta que quede en el futuro,
                // por si el plan estuvo varios ciclos sin ejecutarse.
                $nextDue = $plan->next_due_date->copy();

                do {
                    $nextDue = $nextDue->addDays($plan->frequency_days);
                } while ($nextDue->lte($today));

                $plan->update(['next_due_date' => $nextDue]);

                $this->info("Orden preventiva generada para \"{$plan->equipment->name}\" ({$plan->name}).");
            });

        if ($created === 0) {
            $this->info('No hay planes de mantenimiento vencidos hoy.');
        }

        return self::SUCCESS;
    }
}
