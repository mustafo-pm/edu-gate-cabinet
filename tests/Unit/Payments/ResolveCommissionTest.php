<?php

declare(strict_types=1);

use App\Actions\Payments\ResolveCommission;
use App\Enums\CommissionScope;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Models\CommissionRule;
use App\Models\Merchant;
use App\Models\Psp;

beforeEach(function () {
    $this->psp = Psp::create(['name' => 'PSP', 'code' => 'psp', 'status' => PspStatus::Active]);
    $this->merchant = Merchant::create([
        'name' => 'Uni', 'type' => MerchantType::University, 'status' => MerchantStatus::Active,
        'commission_bps' => 200,
    ]);
});

it('applies a global percentage rule', function () {
    CommissionRule::create(['scope' => CommissionScope::Global, 'rate_bps' => 150, 'priority' => 10, 'is_active' => true]);

    // 1.5% of 1,000,000 tiyin = 15,000.
    expect(app(ResolveCommission::class)->handle($this->psp, $this->merchant, 1_000_000))->toBe(15_000);
});

it('prefers a category rule over a global rule', function () {
    CommissionRule::create(['scope' => CommissionScope::Global, 'rate_bps' => 150, 'priority' => 10, 'is_active' => true]);
    CommissionRule::create([
        'scope' => CommissionScope::Category, 'category' => MerchantType::University,
        'rate_bps' => 100, 'priority' => 5, 'is_active' => true,
    ]);

    // Category (1%) wins over global (1.5%) → 10,000, despite lower priority number.
    expect(app(ResolveCommission::class)->handle($this->psp, $this->merchant, 1_000_000))->toBe(10_000);
});

it('adds a fixed fee on top of the percentage', function () {
    CommissionRule::create(['scope' => CommissionScope::Global, 'rate_bps' => 100, 'fixed_fee' => 5_000, 'priority' => 10, 'is_active' => true]);

    // 1% of 1,000,000 = 10,000, plus 5,000 fixed = 15,000.
    expect(app(ResolveCommission::class)->handle($this->psp, $this->merchant, 1_000_000))->toBe(15_000);
});

it('falls back to the merchant default rate when no rule matches', function () {
    // No CommissionRule rows → uses merchant.commission_bps (2%).
    expect(app(ResolveCommission::class)->handle($this->psp, $this->merchant, 1_000_000))->toBe(20_000);
});
