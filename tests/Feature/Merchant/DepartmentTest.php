<?php

declare(strict_types=1);

use App\Enums\ScheduleStatus;
use App\Enums\StudentStatus;
use App\Livewire\Merchant\Departments;
use App\Models\Department;
use App\Models\PaymentSchedule;
use App\Models\Student;
use App\Support\CabinetRoles;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Faculties and chairs.
 *
 * The grouping is only worth a screen because of what hangs off it, so the
 * tests cover the two things that make it useful and the two that make it
 * safe: the money each department is owed, the tree, and the refusals that
 * stop a department taking its students down with it or swallowing itself.
 */
function faculty(int $merchantId, string $name, ?int $parentId = null): Department
{
    return Department::withoutGlobalScopes()->create([
        'merchant_id' => $merchantId, 'name' => $name, 'parent_id' => $parentId,
    ]);
}

it('creates a department', function () {
    $user = institution();

    Livewire::actingAs($user, 'merchant')
        ->test(Departments::class)
        ->call('startAdding')
        ->set('name', 'Iqtisodiyot fakulteti')
        ->set('code', 'ECON')
        ->call('save')
        ->assertHasNoErrors();

    $d = Department::withoutGlobalScopes()->first();

    expect($d->name)->toBe('Iqtisodiyot fakulteti')
        ->and($d->merchant_id)->toBe($user->merchant_id)
        ->and($d->parent_id)->toBeNull();
});

it('nests a chair under a faculty', function () {
    $user = institution();
    $econ = faculty($user->merchant_id, 'Iqtisodiyot');

    Livewire::actingAs($user, 'merchant')
        ->test(Departments::class)
        ->call('startAdding')
        ->set('name', 'Moliya kafedrasi')
        ->set('parent_id', (string) $econ->id)
        ->call('save')
        ->assertHasNoErrors();

    $chair = Department::withoutGlobalScopes()->where('name', 'Moliya kafedrasi')->first();

    // A bare chair name is ambiguous across faculties.
    expect($chair->parent_id)->toBe($econ->id)
        ->and($chair->path())->toBe('Iqtisodiyot · Moliya kafedrasi');
});

it('refuses to put a department inside itself', function () {
    $user = institution();
    $econ = faculty($user->merchant_id, 'Iqtisodiyot');

    Livewire::actingAs($user, 'merchant')
        ->test(Departments::class)
        ->call('edit', $econ->id)
        ->set('parent_id', (string) $econ->id)
        ->call('save')
        ->assertHasErrors('parent_id');

    expect($econ->fresh()->parent_id)->toBeNull();
});

it('refuses to put a faculty under its own chair', function () {
    $user = institution();
    $econ = faculty($user->merchant_id, 'Iqtisodiyot');
    $chair = faculty($user->merchant_id, 'Moliya', $econ->id);

    // Two clicks would otherwise make every walk of the tree loop forever.
    Livewire::actingAs($user, 'merchant')
        ->test(Departments::class)
        ->call('edit', $econ->id)
        ->set('parent_id', (string) $chair->id)
        ->call('save')
        ->assertHasErrors('parent_id');

    expect($econ->fresh()->parent_id)->toBeNull();
});

it('will not remove a department that still has students', function () {
    $user = institution();
    $econ = faculty($user->merchant_id, 'Iqtisodiyot');

    Student::withoutGlobalScopes()->create([
        'merchant_id' => $user->merchant_id, 'department_id' => $econ->id,
        'student_id_number' => 'STU-1', 'first_name' => 'A', 'last_name' => 'B',
        'status' => StudentStatus::Active,
    ]);

    // Cascading would take the students; nulling their department loses the
    // grouping silently. Reassigning first is the only honest order.
    Livewire::actingAs($user, 'merchant')
        ->test(Departments::class)
        ->call('delete', $econ->id);

    expect(Department::withoutGlobalScopes()->find($econ->id))->not->toBeNull();
});

it('will not remove a faculty that still has chairs', function () {
    $user = institution();
    $econ = faculty($user->merchant_id, 'Iqtisodiyot');
    faculty($user->merchant_id, 'Moliya', $econ->id);

    Livewire::actingAs($user, 'merchant')
        ->test(Departments::class)
        ->call('delete', $econ->id);

    expect(Department::withoutGlobalScopes()->find($econ->id))->not->toBeNull();
});

it('removes an empty department', function () {
    $user = institution();
    $spare = faculty($user->merchant_id, 'Bo\'sh');

    Livewire::actingAs($user, 'merchant')
        ->test(Departments::class)
        ->call('delete', $spare->id);

    expect(Department::withoutGlobalScopes()->find($spare->id))->toBeNull();
});

/**
 * The reason the screen earns its place: accounting can see which faculty is
 * behind without exporting anything.
 */
it('shows what each department has collected and is still owed', function () {
    $user = institution();
    $econ = faculty($user->merchant_id, 'Iqtisodiyot');

    $student = Student::withoutGlobalScopes()->create([
        'merchant_id' => $user->merchant_id, 'department_id' => $econ->id,
        'student_id_number' => 'STU-1', 'first_name' => 'A', 'last_name' => 'B',
        'status' => StudentStatus::Active,
    ]);

    PaymentSchedule::withoutGlobalScopes()->create([
        'merchant_id' => $user->merchant_id, 'student_id' => $student->id,
        'title' => 'Tuition', 'amount' => 6_000_000, 'paid_amount' => 2_000_000,
        'due_date' => '2026-09-10', 'status' => ScheduleStatus::Partial,
    ]);

    // Cancelled schedules are not a debt and must not inflate the figure.
    PaymentSchedule::withoutGlobalScopes()->create([
        'merchant_id' => $user->merchant_id, 'student_id' => $student->id,
        'title' => 'Withdrawn', 'amount' => 9_000_000, 'paid_amount' => 0,
        'due_date' => '2026-09-10', 'status' => ScheduleStatus::Cancelled,
    ]);

    Livewire::actingAs($user, 'merchant')
        ->test(Departments::class)
        ->assertSee('20 000.00')      // collected
        ->assertSee('40 000.00');     // outstanding, cancelled excluded
});

it('never shows another institution its departments', function () {
    $ours = institution();
    $theirs = institution('Rival', 'finance@rival.uz');

    faculty($theirs->merchant_id, 'Maxfiy fakultet');

    Livewire::actingAs($ours, 'merchant')
        ->test(Departments::class)
        ->assertDontSee('Maxfiy fakultet');
});

it('is closed to a role that only reads students', function () {
    $viewer = institution(role: CabinetRoles::VIEWER);

    // Viewing the register is not the same as reshaping it.
    actingAs($viewer, 'merchant')->get('/merchant/departments')->assertForbidden();

    $registrar = institution('Other', 'reg@other.uz', CabinetRoles::REGISTRAR);
    actingAs($registrar, 'merchant')->get('/merchant/departments')->assertOk();
});
