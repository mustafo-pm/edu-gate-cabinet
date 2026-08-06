<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Simulators\Aloqabank\Models\SimPartner;
use App\Simulators\Aloqabank\Models\SimService;
use Illuminate\Database\Seeder;

/**
 * Seeds the simulated bank with the partner and services from Aloqabank's
 * documentation, so the wire format we test against matches their examples.
 *
 * Credentials are the doc's own sample pair (rpay / p@ssw0rd). They authenticate
 * against a fake bank that only exists outside production — there is nothing
 * here worth protecting, and matching the docs makes the examples runnable.
 */
class AloqabankSimulatorSeeder extends Seeder
{
    public function run(): void
    {
        $partner = SimPartner::updateOrCreate(
            ['username' => 'rpay'],
            ['name' => 'EduGate LLC', 'password' => 'p@ssw0rd', 'is_active' => true],
        );

        $services = [
            [
                'id' => 33,
                'name' => 'Расщепления',
                'type' => SimService::CARD_IS_OPTIONAL,
                'account' => '20208000405273320010',
                'balance' => 500_000_000_000,   // 5 000 000 000.00 UZS
            ],
            [
                'id' => 34,
                'name' => 'Оплата по реквизитам UZCARD',
                'type' => SimService::WORKING_WITH_CARD,
                'account' => '20208000405273320011',
                'balance' => 100_000_000_000,
            ],
            [
                'id' => 35,
                'name' => 'Оплата по реквизитам HUMO',
                'type' => SimService::WORKING_WITH_CARD,
                'account' => '20208000405273320012',
                'balance' => 100_000_000_000,
            ],
        ];

        foreach ($services as $service) {
            SimService::updateOrCreate(
                ['id' => $service['id']],
                $service + ['partner_id' => $partner->id, 'activated' => true],
            );
        }
    }
}
