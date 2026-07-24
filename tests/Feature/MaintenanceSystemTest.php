<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MaintenanceSystemTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function makeEquipment(array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'code' => 'EQ-'.uniqid(),
            'name' => 'Equipo de prueba',
            'status' => EquipmentStatus::Operativo,
        ], $overrides));
    }

    public function test_admin_can_create_equipment(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $this->actingAs($admin);

        Volt::test('equipment.index')
            ->set('code', 'EQ-100')
            ->set('name', 'Torno industrial')
            ->set('location', 'Nave 4')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Equipment::where('code', 'EQ-100')->exists());
    }

    public function test_admin_can_inactivate_and_reactivate_equipment(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment(['active' => true]);
        $this->actingAs($admin);

        $component = Volt::test('equipment.index');

        $component->call('toggleActive', $equipment->id);
        $this->assertFalse($equipment->refresh()->active);

        $component->call('toggleActive', $equipment->id);
        $this->assertTrue($equipment->refresh()->active);
    }

    public function test_admin_can_view_equipment_history(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $equipment = $this->makeEquipment(['name' => 'Compresor historial', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'equipment_id' => $equipment->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Alta,
            'status' => WorkOrderStatus::Completada,
            'title' => 'Falla resuelta de prueba',
            'assigned_to' => $tecnico->id,
        ]);

        $this->actingAs($admin)
            ->get('/equipos/'.$equipment->id)
            ->assertOk()
            ->assertSee('Compresor historial')
            ->assertSee('Falla resuelta de prueba')
            ->assertSee($tecnico->name);
    }

    public function test_technicians_and_operators_cannot_access_equipment_history(): void
    {
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $operador = $this->makeUser(UserRole::Operador);
        $equipment = $this->makeEquipment();

        $this->actingAs($tecnico)->get('/equipos/'.$equipment->id)->assertForbidden();
        $this->actingAs($operador)->get('/equipos/'.$equipment->id)->assertForbidden();
    }

    public function test_inactive_equipment_is_excluded_from_new_work_order_reports(): void
    {
        $operador = $this->makeUser(UserRole::Operador);
        $activeEquipment = $this->makeEquipment(['name' => 'Equipo activo']);
        $inactiveEquipment = $this->makeEquipment(['name' => 'Equipo inactivo', 'active' => false]);
        $this->actingAs($operador);

        Volt::test('work-orders.report')
            ->assertSee('Equipo activo')
            ->assertDontSee('Equipo inactivo');
    }

    public function test_technicians_and_operators_cannot_access_equipment_management(): void
    {
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $operador = $this->makeUser(UserRole::Operador);

        $this->actingAs($tecnico)->get('/equipos')->assertForbidden();
        $this->actingAs($operador)->get('/equipos')->assertForbidden();
    }

    public function test_admin_can_create_a_technician_user(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $this->actingAs($admin);

        Volt::test('users.index')
            ->set('name', 'Carlos Méndez')
            ->set('email', 'carlos@parqueindustrial.test')
            ->set('password', 'password123')
            ->set('role', 'tecnico')
            ->call('save')
            ->assertHasNoErrors();

        $technician = User::where('email', 'carlos@parqueindustrial.test')->first();

        $this->assertNotNull($technician);
        $this->assertSame(UserRole::Tecnico, $technician->role);
        $this->assertTrue(Hash::check('password123', $technician->password));
    }

    public function test_admin_can_inactivate_a_user_and_they_can_no_longer_log_in(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $technician = User::factory()->create(['role' => UserRole::Tecnico, 'password' => 'password']);
        $this->actingAs($admin);

        Volt::test('users.index')->call('toggleActive', $technician->id);

        $this->assertFalse($technician->refresh()->active);

        Auth::logout();

        Volt::test('pages.auth.login')
            ->set('form.email', $technician->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors();

        $this->assertGuest();
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $this->actingAs($admin);

        Volt::test('users.index')->call('toggleActive', $admin->id);

        $this->assertTrue($admin->refresh()->active);
    }

    public function test_technicians_and_operators_cannot_access_user_management(): void
    {
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $operador = $this->makeUser(UserRole::Operador);

        $this->actingAs($tecnico)->get('/usuarios')->assertForbidden();
        $this->actingAs($operador)->get('/usuarios')->assertForbidden();
    }

    public function test_admin_can_create_a_maintenance_plan(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment(['created_by' => $admin->id]);
        $this->actingAs($admin);

        Volt::test('maintenance-plans.index')
            ->set('equipment_id', (string) $equipment->id)
            ->set('name', 'Lubricación mensual')
            ->set('frequency_days', '30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(MaintenancePlan::where('name', 'Lubricación mensual')->exists());
    }

    public function test_calendar_plan_link_opens_the_edit_modal_for_that_plan(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment(['created_by' => $admin->id]);
        $plan = MaintenancePlan::create([
            'equipment_id' => $equipment->id,
            'name' => 'Plan enlazado desde calendario',
            'frequency_days' => 15,
            'next_due_date' => now(),
            'active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        $this->get('/planes-mantenimiento?edit='.$plan->id)
            ->assertOk()
            ->assertSee('Plan enlazado desde calendario');
    }

    public function test_operator_can_report_a_failure(): void
    {
        $operador = $this->makeUser(UserRole::Operador);
        $equipment = $this->makeEquipment();
        $this->actingAs($operador);

        Volt::test('work-orders.report')
            ->set('equipment_id', (string) $equipment->id)
            ->set('title', 'Fuga de aceite')
            ->set('priority', 'alta')
            ->call('save');

        $workOrder = WorkOrder::where('title', 'Fuga de aceite')->first();

        $this->assertNotNull($workOrder);
        $this->assertSame($operador->id, $workOrder->reported_by);
        $this->assertSame(WorkOrderStatus::Pendiente, $workOrder->status);
    }

    public function test_technician_can_take_start_and_complete_a_work_order(): void
    {
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $equipment = $this->makeEquipment(['status' => EquipmentStatus::Operativo]);
        $workOrder = WorkOrder::create([
            'equipment_id' => $equipment->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Media,
            'status' => WorkOrderStatus::Pendiente,
            'title' => 'Falla de prueba',
        ]);

        $this->actingAs($tecnico);
        $component = Volt::test('work-orders.show', ['workOrder' => $workOrder]);

        $component->call('take');
        $workOrder->refresh();
        $this->assertSame($tecnico->id, $workOrder->assigned_to);

        $component->call('start');
        $workOrder->refresh();
        $equipment->refresh();
        $this->assertSame(WorkOrderStatus::EnProgreso, $workOrder->status);
        $this->assertSame(EquipmentStatus::EnMantenimiento, $equipment->status);

        $component->set('resolution_notes', 'Se reemplazó el empaque dañado.')->call('complete');
        $workOrder->refresh();
        $equipment->refresh();

        $this->assertSame(WorkOrderStatus::Completada, $workOrder->status);
        $this->assertSame(EquipmentStatus::Operativo, $equipment->status);
        $this->assertGreaterThanOrEqual(3, $workOrder->logs()->count());
    }

    public function test_completing_a_work_order_returns_out_of_service_equipment_to_operational(): void
    {
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $equipment = $this->makeEquipment(['status' => EquipmentStatus::FueraDeServicio]);
        $workOrder = WorkOrder::create([
            'equipment_id' => $equipment->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Alta,
            'status' => WorkOrderStatus::Pendiente,
            'title' => 'Equipo fuera de servicio por falla mayor',
        ]);

        $this->actingAs($tecnico);
        $component = Volt::test('work-orders.show', ['workOrder' => $workOrder]);
        $component->call('take')->call('start');

        // The equipment stays "fuera de servicio" (start() only auto-switches from Operativo).
        $this->assertSame(EquipmentStatus::FueraDeServicio, $equipment->refresh()->status);

        $component->set('resolution_notes', 'Se reparó el componente dañado.')->call('complete');

        $this->assertSame(EquipmentStatus::Operativo, $equipment->refresh()->status);
    }

    public function test_completing_a_preventive_work_order_advances_its_plan_next_due_date(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $equipment = $this->makeEquipment(['created_by' => $admin->id]);
        $plan = MaintenancePlan::create([
            'equipment_id' => $equipment->id,
            'name' => 'Plan con orden real',
            'frequency_days' => 15,
            'next_due_date' => now()->subDay(),
            'active' => true,
            'created_by' => $admin->id,
        ]);
        $workOrder = WorkOrder::create([
            'equipment_id' => $equipment->id,
            'maintenance_plan_id' => $plan->id,
            'type' => WorkOrderType::Preventivo,
            'priority' => WorkOrderPriority::Media,
            'status' => WorkOrderStatus::Pendiente,
            'title' => 'Mantenimiento preventivo: '.$plan->name,
            'scheduled_for' => $plan->next_due_date,
        ]);

        $this->actingAs($tecnico);
        $component = Volt::test('work-orders.show', ['workOrder' => $workOrder]);
        $component->call('take')->call('start');
        $component->set('resolution_notes', 'Revisión completa realizada.')->call('complete');

        $this->assertSame(WorkOrderStatus::Completada, $workOrder->refresh()->status);
        $this->assertTrue($plan->refresh()->next_due_date->isFuture());
    }

    public function test_operator_cannot_open_a_work_order_reported_by_someone_else(): void
    {
        $operador = $this->makeUser(UserRole::Operador);
        $otherUser = $this->makeUser(UserRole::Operador);
        $equipment = $this->makeEquipment();
        $workOrder = WorkOrder::create([
            'equipment_id' => $equipment->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Media,
            'status' => WorkOrderStatus::Pendiente,
            'title' => 'Falla ajena',
            'reported_by' => $otherUser->id,
        ]);

        $this->actingAs($operador);

        Volt::test('work-orders.show', ['workOrder' => $workOrder])->assertForbidden();
    }

    public function test_dashboard_renders_for_every_role(): void
    {
        foreach ([UserRole::Admin, UserRole::Tecnico, UserRole::Operador] as $role) {
            $user = $this->makeUser($role);
            $this->actingAs($user)->get('/dashboard')->assertOk();
        }
    }

    public function test_scheduled_command_generates_work_order_for_due_plan(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment();
        $plan = MaintenancePlan::create([
            'equipment_id' => $equipment->id,
            'name' => 'Plan vencido',
            'frequency_days' => 30,
            'next_due_date' => now()->subDay(),
            'active' => true,
            'created_by' => $admin->id,
        ]);

        $this->artisan('maintenance:generate-work-orders')->assertSuccessful();

        $this->assertTrue(WorkOrder::where('maintenance_plan_id', $plan->id)->exists());
        $this->assertTrue($plan->refresh()->next_due_date->isFuture());
    }

    public function test_calendar_renders_for_every_role(): void
    {
        foreach ([UserRole::Admin, UserRole::Tecnico, UserRole::Operador] as $role) {
            $user = $this->makeUser($role);
            $this->actingAs($user)->get('/calendario')->assertOk();
        }
    }

    public function test_calendar_shows_a_scheduled_work_order_in_the_current_month(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment(['name' => 'Motor calendario', 'created_by' => $admin->id]);
        WorkOrder::create([
            'equipment_id' => $equipment->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Media,
            'status' => WorkOrderStatus::Pendiente,
            'title' => 'Revisión de motor',
            'scheduled_for' => now(),
        ]);

        Volt::actingAs($admin)
            ->test('calendar.index')
            // The chip shows the equipment name; the order title stays as a tooltip.
            ->assertSee('Motor calendario')
            ->assertSee('Revisión de motor');
    }

    public function test_calendar_shows_unscheduled_pending_work_orders(): void
    {
        $operador = $this->makeUser(UserRole::Operador);
        $equipment = $this->makeEquipment();
        WorkOrder::create([
            'equipment_id' => $equipment->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Alta,
            'status' => WorkOrderStatus::Pendiente,
            'title' => 'Fuga detectada sin fecha',
            'reported_by' => $operador->id,
        ]);

        Volt::actingAs($operador)
            ->test('calendar.index')
            ->assertSee('Fuga detectada sin fecha')
            ->assertSee('Pendientes sin programar');
    }

    public function test_admin_can_schedule_an_unscheduled_work_order_from_the_calendar(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment(['created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'equipment_id' => $equipment->id,
            'type' => WorkOrderType::Correctivo,
            'priority' => WorkOrderPriority::Media,
            'status' => WorkOrderStatus::Pendiente,
            'title' => 'Orden por programar',
        ]);

        $targetDate = now()->addDays(3)->format('Y-m-d');

        Volt::actingAs($admin)
            ->test('calendar.index')
            ->set("scheduleDate.{$workOrder->id}", $targetDate)
            ->call('scheduleFor', $workOrder->id);

        $this->assertSame($targetDate, $workOrder->refresh()->scheduled_for->format('Y-m-d'));
        $this->assertSame(1, $workOrder->logs()->count());
    }

    public function test_calendar_shows_maintenance_plan_due_dates_only_for_admin(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $equipment = $this->makeEquipment(['name' => 'Compresor calendario', 'created_by' => $admin->id]);
        MaintenancePlan::create([
            'equipment_id' => $equipment->id,
            'name' => 'Plan de prueba calendario',
            'frequency_days' => 30,
            'next_due_date' => now(),
            'active' => true,
            'created_by' => $admin->id,
        ]);

        Volt::actingAs($admin)->test('calendar.index')->assertSee('Compresor calendario');
        Volt::actingAs($tecnico)->test('calendar.index')->assertDontSee('Compresor calendario');
    }

    public function test_calendar_projects_recurring_plan_occurrences_beyond_the_stored_due_date(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment(['name' => 'Bomba recurrente', 'created_by' => $admin->id]);

        // The stored next_due_date is two months in the past, but with a
        // 10-day frequency an occurrence must still land in the current month.
        MaintenancePlan::create([
            'equipment_id' => $equipment->id,
            'name' => 'Plan cada 10 días',
            'frequency_days' => 10,
            'next_due_date' => now()->subMonths(2)->startOfMonth(),
            'active' => true,
            'created_by' => $admin->id,
        ]);

        Volt::actingAs($admin)->test('calendar.index')->assertSee('Bomba recurrente');
    }

    public function test_admin_can_verify_a_plan_due_today_from_the_calendar(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment(['created_by' => $admin->id]);
        $plan = MaintenancePlan::create([
            'equipment_id' => $equipment->id,
            'name' => 'Plan verificable',
            'frequency_days' => 20,
            'next_due_date' => now(),
            'active' => true,
            'created_by' => $admin->id,
        ]);

        Volt::actingAs($admin)
            ->test('calendar.index')
            ->set('verifyNotes', 'Se lubricaron los rodamientos y quedó en buen estado.')
            ->call('verifyPlan', $plan->id);

        $workOrder = WorkOrder::where('maintenance_plan_id', $plan->id)->first();

        $this->assertNotNull($workOrder);
        $this->assertSame(WorkOrderStatus::Completada, $workOrder->status);
        $this->assertSame(WorkOrderType::Preventivo, $workOrder->type);
        $this->assertSame('Se lubricaron los rodamientos y quedó en buen estado.', $workOrder->resolution_notes);
        $this->assertNotNull($workOrder->completed_at);
        $this->assertSame(1, $workOrder->logs()->count());
        $this->assertTrue($plan->refresh()->next_due_date->isFuture());
        $this->assertSame(now()->format('Y-m-d'), $workOrder->scheduled_for->format('Y-m-d'));

        // The verified work order stays anchored on the calendar (marked as
        // done) instead of disappearing once the plan moves to its next date,
        // and keeps showing the equipment name, not the generic order title.
        Volt::actingAs($admin)
            ->test('calendar.index')
            ->assertSee($equipment->name);
    }

    public function test_technician_cannot_verify_a_plan_from_the_calendar(): void
    {
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment(['created_by' => $admin->id]);
        $plan = MaintenancePlan::create([
            'equipment_id' => $equipment->id,
            'name' => 'Plan protegido',
            'frequency_days' => 20,
            'next_due_date' => now(),
            'active' => true,
            'created_by' => $admin->id,
        ]);

        Volt::actingAs($tecnico)->test('calendar.index')->call('verifyPlan', $plan->id)->assertForbidden();
    }

    public function test_calendar_day_modal_shows_every_work_order_for_a_busy_day(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment(['created_by' => $admin->id]);
        $day = now()->format('Y-m-d');

        foreach (range(1, 5) as $i) {
            WorkOrder::create([
                'equipment_id' => $equipment->id,
                'type' => WorkOrderType::Correctivo,
                'priority' => WorkOrderPriority::Media,
                'status' => WorkOrderStatus::Pendiente,
                'title' => "Falla número {$i}",
                'scheduled_for' => $day,
            ]);
        }

        $component = Volt::actingAs($admin)->test('calendar.index');

        $component->assertSee('+2 más');

        $component->call('showDay', $day);

        foreach (range(1, 5) as $i) {
            $component->assertSee("Falla número {$i}");
        }
    }

    public function test_calendar_navigation_changes_the_displayed_month(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $component = Volt::actingAs($admin)->test('calendar.index');
        $currentMonth = $component->get('month');

        $component->call('nextMonth');
        $this->assertNotSame($currentMonth, $component->get('month'));

        $component->call('previousMonth');
        $this->assertSame($currentMonth, $component->get('month'));
    }

    public function test_technician_can_register_an_equipment_checkout(): void
    {
        $tecnico = $this->makeUser(UserRole::Tecnico);
        $equipment = $this->makeEquipment(['name' => 'Taladro portátil']);

        Volt::actingAs($tecnico)
            ->test('checkouts.index')
            ->set('equipment_id', (string) $equipment->id)
            ->set('taken_by', 'Juan Pérez')
            ->set('destination', 'Obra Nave 3')
            ->set('condition_out', 'bueno')
            ->call('checkout')
            ->assertHasNoErrors();

        $checkout = EquipmentCheckout::where('equipment_id', $equipment->id)->first();

        $this->assertNotNull($checkout);
        $this->assertSame('Juan Pérez', $checkout->taken_by);
        $this->assertTrue($equipment->isCheckedOut());
    }

    public function test_operator_cannot_register_a_checkout_but_can_view_the_bitacora(): void
    {
        $operador = $this->makeUser(UserRole::Operador);
        $equipment = $this->makeEquipment();

        $this->actingAs($operador)->get('/bitacora')->assertOk();

        Volt::actingAs($operador)
            ->test('checkouts.index')
            ->set('equipment_id', (string) $equipment->id)
            ->set('taken_by', 'Alguien')
            ->set('destination', 'Otro lado')
            ->call('checkout')
            ->assertForbidden();
    }

    public function test_equipment_already_checked_out_cannot_be_checked_out_again(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment();
        EquipmentCheckout::create([
            'equipment_id' => $equipment->id,
            'taken_by' => 'Primera persona',
            'destination' => 'Primer destino',
            'condition_out' => 'bueno',
            'checked_out_by' => $admin->id,
            'checked_out_at' => now(),
        ]);

        Volt::actingAs($admin)
            ->test('checkouts.index')
            ->set('equipment_id', (string) $equipment->id)
            ->set('taken_by', 'Segunda persona')
            ->set('destination', 'Segundo destino')
            ->call('checkout')
            ->assertForbidden();

        $this->assertSame(1, EquipmentCheckout::where('equipment_id', $equipment->id)->count());
    }

    public function test_admin_can_register_the_return_of_a_checked_out_equipment(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $equipment = $this->makeEquipment();
        $checkout = EquipmentCheckout::create([
            'equipment_id' => $equipment->id,
            'taken_by' => 'Juan Pérez',
            'destination' => 'Obra Nave 3',
            'condition_out' => 'bueno',
            'checked_out_by' => $admin->id,
            'checked_out_at' => now(),
        ]);

        Volt::actingAs($admin)
            ->test('checkouts.index')
            ->call('openReturnModal', $checkout->id)
            ->set('condition_in', 'regular')
            ->set('return_notes', 'Golpe leve en la carcasa.')
            ->call('confirmReturn');

        $checkout->refresh();

        $this->assertNotNull($checkout->returned_at);
        $this->assertSame('regular', $checkout->condition_in->value);
        $this->assertSame($admin->id, $checkout->returned_by);
        $this->assertFalse($equipment->isCheckedOut());
    }
}
