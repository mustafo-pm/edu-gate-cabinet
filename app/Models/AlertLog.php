<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertLog extends Model
{
    protected $fillable = ['event', 'chat_id', 'message', 'ok', 'error'];

    protected function casts(): array
    {
        return ['ok' => 'boolean'];
    }
}
