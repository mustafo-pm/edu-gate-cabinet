<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Enums\AlertEvent;
use App\Models\MerchantUser;
use App\Support\Alerts;
use App\Support\CabinetRoles;
use App\Support\TempPassword;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Colleagues who can sign in to this institution's cabinet.
 *
 * Three rules do the real work here, and all three exist because the obvious
 * implementation locks somebody out or hands somebody too much:
 *
 *  • Only an owner may create or demote an owner. Otherwise anyone allowed to
 *    add colleagues can promote themselves to the role that moves bank
 *    accounts.
 *  • Nobody may change their own role or switch themselves off.
 *  • The last owner cannot be removed or demoted — an institution with no
 *    owner cannot appoint one, and only support can dig it out.
 */
#[Layout('merchant.layout')]
class Staff extends Component
{
    public bool $inviting = false;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = CabinetRoles::VIEWER;

    /** Shown once after an account is created or reset. Never stored. */
    public ?string $issuedPassword = null;

    public ?string $issuedFor = null;

    public function invite(): void
    {
        $this->authorizeRole($this->role);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('merchant_users', 'email')],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(CabinetRoles::assignable())],
        ]);

        $password = TempPassword::generate();

        $user = MerchantUser::create([
            'merchant_id' => $this->me()->merchant_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'password' => $password,
            'is_active' => true,
            // The difference between a temporary password and a password
            // somebody else knows.
            'must_change_password' => true,
        ]);

        $user->assignRole($data['role']);

        Alerts::raise(AlertEvent::UserCreated, [
            'email' => $user->email,
            'merchant' => $this->me()->merchant?->name,
        ]);

        // Handed over by the person who invited them, not emailed and not put
        // in a chat that keeps its history forever.
        $this->issuedPassword = $password;
        $this->issuedFor = $user->email;

        $this->reset(['name', 'email', 'phone', 'inviting']);
        $this->role = CabinetRoles::VIEWER;
    }

    public function changeRole(int $id, string $role): void
    {
        $user = $this->colleague($id);

        $this->authorizeRole($role);
        $this->authorizeRole($user->roles->first()?->name ?? CabinetRoles::VIEWER);
        $this->guardSelf($user);
        $this->guardLastOwner($user, becoming: $role);

        $user->syncRoles([$role]);

        session()->flash('status', __('cabinet.staff.role_changed'));
    }

    public function resetPassword(int $id): void
    {
        $user = $this->colleague($id);

        $this->authorizeRole($user->roles->first()?->name ?? CabinetRoles::VIEWER);

        $this->issuedPassword = TempPassword::issue($user);
        $this->issuedFor = $user->email;
    }

    public function toggleActive(int $id): void
    {
        $user = $this->colleague($id);

        $this->guardSelf($user);
        $this->authorizeRole($user->roles->first()?->name ?? CabinetRoles::VIEWER);

        if ($user->is_active) {
            $this->guardLastOwner($user, becoming: null);
        }

        $user->update(['is_active' => ! $user->is_active]);

        session()->flash('status', __('cabinet.staff.saved'));
    }

    public function dismissPassword(): void
    {
        $this->reset(['issuedPassword', 'issuedFor']);
    }

    public function render()
    {
        return view('livewire.merchant.staff', [
            'staff' => MerchantUser::where('merchant_id', $this->me()->merchant_id)
                ->with('roles')->orderBy('name')->get(),
            'roles' => CabinetRoles::assignable(),
            'isOwner' => $this->me()->hasRole(CabinetRoles::OWNER),
            'me' => $this->me(),
        ])->title(__('cabinet.staff.title'));
    }

    /**
     * MerchantUser has no tenant global scope, so this filter is the isolation
     * — an id typed into the browser must not reach another institution.
     */
    private function colleague(int $id): MerchantUser
    {
        return MerchantUser::where('merchant_id', $this->me()->merchant_id)
            ->with('roles')
            ->findOrFail($id);
    }

    /** Only an owner may hand out — or take away — the owner role. */
    private function authorizeRole(string $role): void
    {
        if ($role === CabinetRoles::OWNER && ! $this->me()->hasRole(CabinetRoles::OWNER)) {
            abort(403, 'Only an owner can manage owners.');
        }
    }

    private function guardSelf(MerchantUser $user): void
    {
        if ($user->is($this->me())) {
            abort(403, 'You cannot change your own access.');
        }
    }

    /** An institution with no owner cannot appoint one. */
    private function guardLastOwner(MerchantUser $user, ?string $becoming): void
    {
        if (! $user->hasRole(CabinetRoles::OWNER) || $becoming === CabinetRoles::OWNER) {
            return;
        }

        $owners = MerchantUser::where('merchant_id', $user->merchant_id)
            ->where('is_active', true)
            ->role(CabinetRoles::OWNER)
            ->count();

        if ($owners <= 1) {
            abort(403, 'This is the last owner.');
        }
    }

    private function me(): MerchantUser
    {
        return auth('merchant')->user();
    }
}
