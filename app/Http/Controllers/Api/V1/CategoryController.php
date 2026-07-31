<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;

class CategoryController extends ApiController
{
    /** GET /api/v1/categories — institution categories with counts. */
    public function index(): JsonResponse
    {
        $categories = collect(MerchantType::cases())->map(fn (MerchantType $t) => [
            'id' => $t->value,
            'name' => $t->label(),
            'institutions_count' => Merchant::where('type', $t->value)
                ->where('status', MerchantStatus::Active)
                ->count(),
        ]);

        return $this->ok($categories->all());
    }

    /** GET /api/v1/categories/{category}/institutions */
    public function institutions(string $category): JsonResponse
    {
        $type = MerchantType::tryFrom($category);
        if (! $type) {
            return $this->error('unknown_category', 'Unknown institution category.', 404);
        }

        $institutions = Merchant::where('type', $type->value)
            ->where('status', MerchantStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (Merchant $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $m->type->value,
            ]);

        return $this->ok($institutions->all());
    }
}
