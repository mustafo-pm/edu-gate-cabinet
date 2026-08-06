<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime configuration the team can change without a deploy.
 *
 * Mail is the first tenant of this table: SMTP credentials belong to whoever
 * runs the platform, not to whoever last edited .env, and needing an SSH
 * session to change a mail password is how outgoing mail stays broken.
 *
 * Values that are secrets are encrypted at rest — see Settings::SECRET_KEYS.
 * The DB is backed up and dumped routinely, and an SMTP password sitting in
 * plaintext there travels with every copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
