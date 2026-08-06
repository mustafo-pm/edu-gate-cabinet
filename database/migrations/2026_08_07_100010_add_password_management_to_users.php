<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin issue a temporary password that must be replaced on first use.
 *
 * `must_change_password` is what makes a temporary password temporary. Without
 * it, "temp" describes only the intent of whoever typed it, and the credential
 * an operator read out over the phone stays valid indefinitely.
 *
 * `password_changed_at` exists so the admin screens can show whether a
 * temporary password was ever actually used, rather than leaving it ambiguous.
 */
return new class extends Migration
{
    private const TABLES = ['merchant_users', 'psp_users', 'admin_users'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('must_change_password')->default(false)->after('password');
                $t->timestamp('password_changed_at')->nullable()->after('must_change_password');
            });
        }

        // Existing accounts chose their own passwords, so nothing is pending.
        foreach (self::TABLES as $table) {
            DB::table($table)->update(['password_changed_at' => now()]);
        }

        DB::table('alert_rules')->insert([
            [
                'event' => 'user_created', 'is_enabled' => true, 'threshold' => null,
                'send_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'event' => 'password_reset', 'is_enabled' => true, 'threshold' => null,
                'send_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['must_change_password', 'password_changed_at']);
            });
        }

        DB::table('alert_rules')->whereIn('event', ['user_created', 'password_reset'])->delete();
    }
};
