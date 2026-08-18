<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Enums\MerchantContactKind;
use App\Models\Merchant;
use App\Models\MerchantContact;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The institution's own profile: what it is called, how it looks, who to call.
 *
 * Bank accounts are deliberately NOT here — they decide where money lands and
 * live on their own page with an approval step. Everything on this page is
 * safe for an institution to change about itself.
 */
#[Layout('merchant.layout')]
class Profile extends Component
{
    use WithFileUploads;

    // Identity
    public string $name_uz = '';

    public string $name_ru = '';

    public string $name_en = '';

    public string $legal_name = '';

    public string $stir = '';

    public string $address = '';

    public string $website_url = '';

    public bool $show_on_receipt = false;

    // Branding
    public $logoLight;

    public $logoDark;

    public $banner;

    // Contacts
    public array $contacts = [];

    public function mount(): void
    {
        $m = $this->merchant();

        $this->name_uz = (string) ($m->name_uz ?: $m->name);
        $this->name_ru = (string) $m->name_ru;
        $this->name_en = (string) $m->name_en;
        $this->legal_name = (string) $m->legal_name;
        $this->stir = (string) $m->stir;
        $this->address = (string) $m->address;
        $this->website_url = (string) $m->website_url;
        $this->show_on_receipt = (bool) $m->show_on_receipt;

        $this->loadContacts();
    }

    public function save(): void
    {
        $data = $this->validate([
            'name_uz' => ['required', 'string', 'max:255'],
            'name_ru' => ['nullable', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'stir' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'show_on_receipt' => ['boolean'],
        ]);

        $this->merchant()->update($data);

        session()->flash('status', __('cabinet.profile.saved'));
    }

    public function saveLogos(): void
    {
        $this->validate([
            // SVG excluded: it is a script container, and these are rendered on
            // a public receipt where a stranger's browser runs whatever is in
            // them.
            'logoLight' => ['nullable', 'image', 'mimes:png,jpg,webp', 'max:1024'],
            'logoDark' => ['nullable', 'image', 'mimes:png,jpg,webp', 'max:1024'],
            'banner' => ['nullable', 'image', 'mimes:png,jpg,webp', 'max:2048'],
        ]);

        $m = $this->merchant();
        $changes = [];

        foreach ([
            'logoLight' => 'logo_light_path',
            'logoDark' => 'logo_dark_path',
            'banner' => 'banner_path',
        ] as $prop => $column) {
            if ($this->{$prop}) {
                $changes[$column] = $this->{$prop}->store('merchants/'.$m->id, 'public');
            }
        }

        if ($changes !== []) {
            $m->update($changes);
            $this->reset(['logoLight', 'logoDark', 'banner']);
            session()->flash('status', __('cabinet.profile.logos_saved'));
        }
    }

    public function removeLogo(string $which): void
    {
        $column = match ($which) {
            'light' => 'logo_light_path',
            'dark' => 'logo_dark_path',
            'banner' => 'banner_path',
            default => null,
        };

        if ($column) {
            // The file itself is left on disk: a receipt rendered before this
            // moment may still reference it, and reclaiming a few kilobytes is
            // not worth a broken image on somebody's document.
            $this->merchant()->update([$column => null]);
        }
    }

    // ── Contacts ────────────────────────────────────────────────────────

    public function addContact(): void
    {
        $this->contacts[] = [
            'id' => null,
            'kind' => MerchantContactKind::Accounting->value,
            'title' => '',
            'person_name' => '',
            'phone' => '',
            'email' => '',
            'is_public' => false,
        ];
    }

    public function saveContacts(): void
    {
        $this->validate([
            'contacts.*.kind' => ['required', 'string'],
            'contacts.*.title' => ['nullable', 'string', 'max:255'],
            'contacts.*.person_name' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:40'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
        ]);

        foreach ($this->contacts as $i => $row) {
            $attrs = [
                'kind' => $row['kind'],
                'title' => $row['title'] ?: null,
                'person_name' => $row['person_name'] ?: null,
                'phone' => $row['phone'] ?: null,
                'email' => $row['email'] ?: null,
                'is_public' => (bool) $row['is_public'],
                'sort_order' => $i,
            ];

            if ($row['id']) {
                // findOrFail through the tenant scope: an edited id from the
                // browser cannot reach another institution's contact.
                MerchantContact::findOrFail($row['id'])->update($attrs);
            } else {
                MerchantContact::create($attrs);   // merchant_id set by the scope
            }
        }

        $this->loadContacts();
        session()->flash('status', __('cabinet.profile.contacts_saved'));
    }

    public function removeContact(int $index): void
    {
        $row = $this->contacts[$index] ?? null;

        if ($row && $row['id']) {
            MerchantContact::findOrFail($row['id'])->delete();
        }

        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
    }

    public function render()
    {
        return view('livewire.merchant.profile', [
            'merchant' => $this->merchant(),
            'kinds' => MerchantContactKind::options(),
        ])->title(__('cabinet.profile.title'));
    }

    private function loadContacts(): void
    {
        $this->contacts = MerchantContact::orderBy('sort_order')->get()
            ->map(fn (MerchantContact $c) => [
                'id' => $c->id,
                'kind' => $c->kind->value,
                'title' => (string) $c->title,
                'person_name' => (string) $c->person_name,
                'phone' => (string) $c->phone,
                'email' => (string) $c->email,
                'is_public' => (bool) $c->is_public,
            ])->all();
    }

    private function merchant(): Merchant
    {
        return auth('merchant')->user()->merchant;
    }
}
