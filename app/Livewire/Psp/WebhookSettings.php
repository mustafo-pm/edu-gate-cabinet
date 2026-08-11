<?php

declare(strict_types=1);

namespace App\Livewire\Psp;

use App\Models\Psp;
use App\Models\WebhookDelivery;
use App\Support\Webhooks;
use App\Support\WebhookUrl;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('partner.layout')]
#[Title('Webhooks')]
class WebhookSettings extends Component
{
    public string $url = '';

    public bool $enabled = false;

    /** Plaintext secret shown exactly once, like an API key. */
    public ?string $newSecret = null;

    public function mount(): void
    {
        $psp = $this->psp();

        $this->url = (string) ($psp->webhook_url ?? '');
        $this->enabled = (bool) $psp->webhook_enabled;
    }

    public function save(): void
    {
        $this->validate([
            'url' => ['required', 'url', 'max:500'],
        ]);

        // The address is checked before it is ever stored, so an obviously
        // unusable one is refused while the person who typed it is still here
        // to fix it. DeliverWebhook checks again at send time — see WebhookUrl.
        if ($reason = WebhookUrl::reject($this->url)) {
            throw ValidationException::withMessages([
                'url' => __('cabinet.webhooks.error_'.$reason),
            ]);
        }

        $psp = $this->psp();

        // Turning delivery on without a secret would send unsigned callbacks a
        // PSP has no way to trust, so the secret is issued with the address.
        if (blank($psp->webhook_secret)) {
            $this->newSecret = Webhooks::freshSecret();
            $psp->webhook_secret = $this->newSecret;
        }

        $psp->webhook_url = $this->url;
        $psp->webhook_enabled = $this->enabled;
        $psp->save();

        session()->flash('status', __('cabinet.webhooks.saved'));
    }

    /**
     * Issue a new signing secret.
     *
     * The old one stops working the moment this is saved, so anything in flight
     * fails its signature check and retries with the new one.
     */
    public function rotateSecret(): void
    {
        $psp = $this->psp();

        $this->newSecret = Webhooks::freshSecret();
        $psp->webhook_secret = $this->newSecret;
        $psp->save();

        session()->flash('status', __('cabinet.webhooks.rotated'));
    }

    public function dismissSecret(): void
    {
        $this->newSecret = null;
    }

    /** Send a harmless event so a PSP can prove their endpoint works. */
    public function sendTest(): void
    {
        $psp = $this->psp();

        if (! Webhooks::configured($psp)) {
            session()->flash('status', __('cabinet.webhooks.not_configured'));

            return;
        }

        Webhooks::send($psp, 'ping', [
            'message' => 'EduGate webhook test',
            'psp' => $psp->code,
        ]);

        session()->flash('status', __('cabinet.webhooks.test_queued'));
    }

    public function render()
    {
        return view('livewire.psp.webhook-settings', [
            'hasSecret' => filled($this->psp()->webhook_secret),
            'deliveries' => WebhookDelivery::query()->latest('id')->limit(20)->get(),
        ]);
    }

    private function psp(): Psp
    {
        return auth('psp')->user()->psp;
    }
}
