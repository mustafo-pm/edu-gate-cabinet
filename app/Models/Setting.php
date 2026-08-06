<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One runtime configuration value.
 *
 * Audited, so a change to where mail goes from — or to any future setting that
 * affects money — is attributable. The value itself is redacted from the audit
 * trail when it is a secret; recording the SMTP password in `audits` would
 * defeat encrypting it in `settings`.
 */
class Setting extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = ['key', 'value', 'is_encrypted'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    /** @var array<int, string> Never written to the audit trail. */
    protected $auditExclude = ['value'];
}
