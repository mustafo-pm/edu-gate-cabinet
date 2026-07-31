<?php

declare(strict_types=1);

namespace App\Livewire\Psp;

use App\Enums\ApiEnvironment;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('partner.layout')]
#[Title('API keys')]
class ApiKeys extends Component
{
    public string $name = '';
    public string $environment = 'sandbox';

    /** Plaintext secret shown exactly once after generation. */
    public ?string $newSecret = null;
    public ?string $newKeyId = null;

    public function generate(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'environment' => ['required', 'in:sandbox,live'],
        ]);

        $env = ApiEnvironment::from($this->environment);
        $secret = ($env === ApiEnvironment::Live ? 'sk_live_' : 'sk_sandbox_').Str::random(40);
        $keyId = 'egk_'.Str::random(24);

        ApiKey::create([
            'name' => $this->name,
            'key_id' => $keyId,
            'secret_hash' => Hash::make($secret),
            'environment' => $env,
        ]); // psp_id auto-set by scope

        // Surface once; never retrievable again.
        $this->newSecret = $secret;
        $this->newKeyId = $keyId;
        $this->reset('name');
    }

    public function revoke(int $id): void
    {
        $key = ApiKey::findOrFail($id); // scope guarantees it belongs to this PSP
        if ($key->isActive()) {
            $key->update(['revoked_at' => now()]);
            session()->flash('status', __('cabinet.keys.revoked_flash'));
        }
    }

    public function dismissSecret(): void
    {
        $this->newSecret = null;
        $this->newKeyId = null;
    }

    public function render()
    {
        return view('livewire.psp.api-keys', [
            'keys' => ApiKey::latest('id')->get(),
        ]);
    }
}
