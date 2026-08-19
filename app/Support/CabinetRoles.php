<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MerchantUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may do what inside an institution's cabinet.
 *
 * Roles are per GUARD, not per institution, and spatie's teams mode stays off.
 * Tenancy and authorisation are different axes here: ScopedToMerchant already
 * decides whose data you can see, so a role only has to say what you may do
 * with your own. An accountant at one university and an accountant at another
 * share this definition and still cannot see each other's students.
 *
 * The permission list is deliberately short. Every entry maps to a screen or a
 * button somebody actually presses; a permission nobody checks is a promise the
 * product does not keep.
 */
final class CabinetRoles
{
    public const GUARD = 'merchant';

    // Money and access. Kept apart from the rest on purpose — these two are the
    // ones that can cost an institution something.
    public const BANK_ACCOUNTS = 'bank_accounts.manage';

    public const STAFF = 'staff.manage';

    public const PROFILE = 'profile.manage';

    public const STUDENTS_VIEW = 'students.view';

    public const STUDENTS_MANAGE = 'students.manage';

    public const SCHEDULES = 'schedules.manage';

    public const PAYMENTS_VIEW = 'payments.view';

    public const RECEIPTS = 'receipts.issue';

    public const REPORTS = 'reports.view';

    public const OWNER = 'owner';

    public const ACCOUNTANT = 'accountant';

    public const REGISTRAR = 'registrar';

    public const VIEWER = 'viewer';

    /** @return array<int, string> */
    public static function permissions(): array
    {
        return [
            self::STUDENTS_VIEW, self::STUDENTS_MANAGE, self::SCHEDULES,
            self::PAYMENTS_VIEW, self::RECEIPTS, self::REPORTS,
            self::PROFILE, self::BANK_ACCOUNTS, self::STAFF,
        ];
    }

    /** @return array<string, array<int, string>> role => permissions */
    public static function roles(): array
    {
        return [
            // The account an institution is handed at onboarding. The only role
            // that can move where money lands or add colleagues.
            self::OWNER => self::permissions(),

            self::ACCOUNTANT => [
                self::PAYMENTS_VIEW, self::RECEIPTS, self::SCHEDULES,
                self::REPORTS, self::STUDENTS_VIEW,
            ],

            // Student affairs: the register is theirs, the money is not.
            self::REGISTRAR => [
                self::STUDENTS_VIEW, self::STUDENTS_MANAGE,
                self::SCHEDULES, self::PAYMENTS_VIEW,
            ],

            self::VIEWER => [
                self::STUDENTS_VIEW, self::PAYMENTS_VIEW, self::REPORTS,
            ],
        ];
    }

    /** Roles a cabinet user may hand out, in the order they are offered. */
    public static function assignable(): array
    {
        return [self::OWNER, self::ACCOUNTANT, self::REGISTRAR, self::VIEWER];
    }

    public static function label(string $role): string
    {
        return __('cabinet.staff.role_'.$role);
    }

    /**
     * Create every permission and role that does not exist yet.
     *
     * Idempotent, and additive only: it never removes a permission from a role,
     * because an institution may have tuned one and a deploy should not quietly
     * undo that.
     */
    public static function sync(): void
    {
        foreach (self::permissions() as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        foreach (self::roles() as $role => $permissions) {
            $model = Role::findOrCreate($role, self::GUARD);

            $missing = array_diff($permissions, $model->permissions->pluck('name')->all());

            if ($missing !== []) {
                $model->givePermissionTo($missing);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Give every role-less cabinet user the owner role.
     *
     * Run once after roles are introduced. Before this feature nobody had a
     * role and everybody could do everything, so the accounts that exist today
     * ARE the owners — starting them at anything less would lock institutions
     * out of their own cabinets on the deploy that added permissions.
     *
     * @return int users changed
     */
    public static function backfillOwners(): int
    {
        $changed = 0;

        MerchantUser::query()->with('roles')->each(function (MerchantUser $user) use (&$changed) {
            if ($user->roles->isEmpty()) {
                $user->assignRole(self::OWNER);
                $changed++;
            }
        });

        return $changed;
    }
}
