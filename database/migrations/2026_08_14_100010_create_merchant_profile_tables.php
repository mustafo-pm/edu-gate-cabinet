<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A real institution profile: names in three languages, branding, several bank
 * accounts and several contact points.
 *
 * The bank accounts are the part with teeth. An institution changes bank —
 * Webster went from Davr to Ipak Yuli — and for a while both are live before
 * the old one is retired. A single `bank_account` column on `merchants` cannot
 * hold that, and worse, editing it in place silently redirects money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            // The display name in each language the cabinet and the receipt
            // offer. `name` stays as the fallback and the admin-facing label.
            $table->string('name_uz')->nullable()->after('name');
            $table->string('name_ru')->nullable()->after('name_uz');
            $table->string('name_en')->nullable()->after('name_ru');

            $table->string('website_url')->nullable()->after('contact_email');
            $table->string('address')->nullable()->after('website_url');

            // Two logos rather than one: a mark drawn for a white page
            // disappears on a dark one, and the receipt and cabinet both have a
            // dark mode.
            $table->string('logo_light_path')->nullable()->after('address');
            $table->string('logo_dark_path')->nullable()->after('logo_light_path');
            $table->string('banner_path')->nullable()->after('logo_dark_path');

            // What the institution agrees to show on a document a stranger
            // holds. Off by default — publishing someone's mark is a decision.
            $table->boolean('show_on_receipt')->default(false)->after('banner_path');
        });

        Schema::create('merchant_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();

            $table->string('label')->nullable();          // "Kontrakt to'lovlari"
            $table->string('bank_name');
            $table->string('mfo', 20);
            $table->string('account_number', 30);
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            /*
             * An institution may propose an account; only EduGate may let it
             * receive money. Without that step one compromised cabinet password
             * redirects every settlement to an attacker's account, and the first
             * anyone notices is when the university asks where its term is.
             */
            $table->string('status', 20)->default('pending');   // MerchantBankAccountStatus
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();

            // Where settlements go. Exactly one per merchant, enforced in the model.
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->unique(['merchant_id', 'mfo', 'account_number']);
            $table->index(['merchant_id', 'status']);
        });

        Schema::create('merchant_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();

            $table->string('kind', 30);                   // MerchantContactKind
            $table->string('title')->nullable();          // overrides the kind's label
            $table->string('person_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();

            // A payer with a question needs somewhere to call; the accounting
            // desk's direct line is not that place.
            $table->boolean('is_public')->default(false);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['merchant_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_contacts');
        Schema::dropIfExists('merchant_bank_accounts');

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'name_uz', 'name_ru', 'name_en', 'website_url', 'address',
                'logo_light_path', 'logo_dark_path', 'banner_path', 'show_on_receipt',
            ]);
        });
    }
};
