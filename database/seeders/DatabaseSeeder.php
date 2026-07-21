<?php

namespace Database\Seeders;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Administrador del Parque',
            'email' => 'admin@parqueindustrial.test',
            'role' => UserRole::Admin,
        ]);

        $tecnico1 = User::factory()->create([
            'name' => 'Carlos Méndez',
            'email' => 'tecnico1@parqueindustrial.test',
            'role' => UserRole::Tecnico,
        ]);

        $tecnico2 = User::factory()->create([
            'name' => 'Ana Rodríguez',
            'email' => 'tecnico2@parqueindustrial.test',
            'role' => UserRole::Tecnico,
        ]);

        $operador = User::factory()->create([
            'name' => 'Inquilino Nave 3',
            'email' => 'operador@parqueindustrial.test',
            'role' => UserRole::Operador,
        ]);

        $equipos = [
            ['code' => 'EQ-001', 'name' => 'Compresor de aire industrial', 'category' => 'Neumática', 'brand' => 'Atlas Copco', 'location' => 'Nave 1', 'status' => EquipmentStatus::Operativo],
            ['code' => 'EQ-002', 'name' => 'Montacargas eléctrico', 'category' => 'Maquinaria pesada', 'brand' => 'Toyota', 'location' => 'Nave 1', 'status' => EquipmentStatus::Operativo],
            ['code' => 'EQ-003', 'name' => 'Puente grúa 5 ton', 'category' => 'Maquinaria pesada', 'brand' => 'Demag', 'location' => 'Nave 2', 'status' => EquipmentStatus::EnMantenimiento],
            ['code' => 'EQ-004', 'name' => 'Tablero eléctrico principal', 'category' => 'Eléctrico', 'brand' => 'Schneider', 'location' => 'Subestación', 'status' => EquipmentStatus::Operativo],
            ['code' => 'EQ-005', 'name' => 'Aire acondicionado industrial', 'category' => 'HVAC', 'brand' => 'Carrier', 'location' => 'Nave 3', 'status' => EquipmentStatus::FueraDeServicio],
            ['code' => 'EQ-006', 'name' => 'Bomba de agua contra incendio', 'category' => 'Seguridad', 'brand' => 'Grundfos', 'location' => 'Cuarto de bombas', 'status' => EquipmentStatus::Operativo],
        ];

        $equipmentModels = collect($equipos)->map(fn (array $data) => Equipment::create([
            ...$data,
            'created_by' => $admin->id,
        ]));

        $plan1 = MaintenancePlan::create([
            'equipment_id' => $equipmentModels[0]->id,
            'name' => 'Cambio de filtros y lubricación',
            'checklist' => "1. Revisar nivel de aceite\n2. Cambiar filtro de aire\n3. Verificar presión de trabajo",
            'frequency_days' => 30,
            'next_due_date' => now()->subDays(2),
            'created_by' => $admin->id,
        ]);

        MaintenancePlan::create([
            'equipment_id' => $equipmentModels[3]->id,
            'name' => 'Revisión eléctrica trimestral',
            'checklist' => "1. Termografía de tablero\n2. Ajuste de conexiones\n3. Prueba de disyuntores",
            'frequency_days' => 90,
            'next_due_date' => now()->addDays(25),
            'created_by' => $admin->id,
        ]);

        MaintenancePlan::create([
            'equipment_id' => $equipmentModels[5]->id,
            'name' => 'Prueba de bomba contra incendio',
            'checklist' => "1. Encendido de prueba\n2. Verificar presión\n3. Revisar batería de arranque",
            'frequency_days' => 15,
            'next_due_date' => now()->addDays(5),
            'created_by' => $admin->id,
        ]);

        // Órdenes de ejemplo con distintos estados.
        WorkOrder::create([
            'equipment_id' => $equipmentModels[2]->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Alta,
            'status' => WorkOrderStatus::EnProgreso,
            'title' => 'Ruido anormal en el motor del puente grúa',
            'description' => 'Se escucha un ruido metálico al elevar carga.',
            'reported_by' => $operador->id,
            'assigned_to' => $tecnico1->id,
            'started_at' => now()->subHours(3),
        ]);

        WorkOrder::create([
            'equipment_id' => $equipmentModels[4]->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Urgente,
            'status' => WorkOrderStatus::Pendiente,
            'title' => 'Aire acondicionado no enfría',
            'description' => 'El equipo enciende pero no enfría desde ayer.',
            'reported_by' => $operador->id,
            'scheduled_for' => now()->subDay(),
        ]);

        WorkOrder::create([
            'equipment_id' => $equipmentModels[0]->id,
            'maintenance_plan_id' => $plan1->id,
            'type' => WorkOrderType::Preventivo,
            'priority' => WorkOrderPriority::Media,
            'status' => WorkOrderStatus::Completada,
            'title' => 'Mantenimiento preventivo: Cambio de filtros y lubricación',
            'description' => $plan1->checklist,
            'resolution_notes' => 'Se cambiaron filtros y se lubricaron rodamientos sin novedad.',
            'assigned_to' => $tecnico2->id,
            'started_at' => now()->subDays(32),
            'completed_at' => now()->subDays(32)->addHours(2),
        ]);

        WorkOrder::create([
            'equipment_id' => $equipmentModels[1]->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Baja,
            'status' => WorkOrderStatus::Asignada,
            'title' => 'Batería del montacargas descarga rápido',
            'description' => 'La batería no dura la jornada completa.',
            'reported_by' => $operador->id,
            'assigned_to' => $tecnico1->id,
        ]);
    }
}
