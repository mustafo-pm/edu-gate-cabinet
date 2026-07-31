@php
    use App\Enums\ScheduleStatus;
    $all = \App\Models\Student::count();
    $overdue = \App\Models\PaymentSchedule::whereIn('status', [ScheduleStatus::Unpaid->value, ScheduleStatus::Overdue->value])
        ->distinct('student_id')->count('student_id');
    $samples = [
        'acceptance' => "Hurmatli ota-ona, farzandingiz uchun 6 000 000 UZS toʻlov qabul qilindi. Rahmat!",
        'missing'    => "Hurmatli ota-ona, farzandingizning sentabr oyi oʻqish toʻlovi hali amalga oshirilmagan.",
        'deadline'   => __('ext.msg.sample'),
        'custom'     => '',
    ];
@endphp

<div class="space-y-6"
     x-data="{
        audience: 'all',
        channels: { email: true, telegram: true, sms: false },
        template: 'deadline',
        custom: '',
        counts: { all: {{ $all }}, overdue: {{ $overdue }}, department: {{ $all }}, selected: 0 },
        samples: {{ Illuminate\Support\Js::from($samples) }},
        get body() { return this.template === 'custom' ? this.custom : this.samples[this.template]; },
        get recipients() { return this.counts[this.audience] ?? 0; },
     }">

    <x-eg.demo-banner />
    <p class="-mt-2 text-sm text-eg-muted">{{ __('ext.msg.subtitle') }}</p>

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Compose --}}
        <div class="space-y-5 lg:col-span-3">
            {{-- Audience --}}
            <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
                <p class="mb-3 text-sm font-semibold text-eg-ink">{{ __('ext.msg.audience') }}</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (['all' => __('ext.msg.aud_all'), 'overdue' => __('ext.msg.aud_overdue'), 'department' => __('ext.msg.aud_department'), 'selected' => __('ext.msg.aud_selected')] as $k => $lbl)
                        <button type="button" @click="audience='{{ $k }}'"
                                :class="audience==='{{ $k }}' ? 'border-eg-blue bg-eg-blue/5 text-eg-ink' : 'border-eg-border text-eg-text hover:bg-eg-surface2'"
                                class="flex items-center justify-between rounded-lg border px-3 py-2.5 text-sm transition">
                            <span>{{ $lbl }}</span>
                            <span class="rounded-full bg-eg-surface2 px-2 py-0.5 text-xs font-semibold text-eg-muted" x-text="counts['{{ $k }}']"></span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Channels --}}
            <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
                <p class="mb-3 text-sm font-semibold text-eg-ink">{{ __('ext.msg.channels') }}</p>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach ([['email','ext.msg.email','📧'], ['telegram','ext.msg.telegram','✈️'], ['sms','ext.msg.sms','📱']] as [$ch, $lbl, $emoji])
                        <button type="button" @click="channels.{{ $ch }} = !channels.{{ $ch }}"
                                :class="channels.{{ $ch }} ? 'border-eg-blue bg-eg-blue/5' : 'border-eg-border hover:bg-eg-surface2'"
                                class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 text-sm text-eg-text transition">
                            <span class="text-base">{{ $emoji }}</span>
                            <span class="flex-1 text-left">{{ __($lbl) }}</span>
                            <span :class="channels.{{ $ch }} ? 'bg-eg-blue border-eg-blue' : 'border-eg-border'"
                                  class="grid h-4 w-4 place-items-center rounded border">
                                <svg x-show="channels.{{ $ch }}" class="h-3 w-3 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Template + message --}}
            <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
                <p class="mb-3 text-sm font-semibold text-eg-ink">{{ __('ext.msg.template') }}</p>
                <div class="mb-4 flex flex-wrap gap-2">
                    @foreach (['acceptance' => 'ext.msg.tmpl_acceptance', 'missing' => 'ext.msg.tmpl_missing', 'deadline' => 'ext.msg.tmpl_deadline', 'custom' => 'ext.msg.tmpl_custom'] as $k => $lbl)
                        <button type="button" @click="template='{{ $k }}'"
                                :class="template==='{{ $k }}' ? 'bg-eg-blue text-white' : 'bg-eg-surface2 text-eg-text hover:bg-eg-border'"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition">{{ __($lbl) }}</button>
                    @endforeach
                </div>
                <label class="mb-1.5 block text-sm font-medium text-eg-ink">{{ __('ext.msg.message') }}</label>
                <textarea rows="4" class="eg-input"
                          x-model="custom"
                          x-effect="if(template!=='custom' && $el!==document.activeElement){ $el.value = body }"
                          x-init="$el.value = body"
                          @input="if(template!=='custom'){ template='custom'; custom=$event.target.value }"></textarea>

                <div class="mt-4 flex items-center justify-between">
                    <span class="text-sm text-eg-muted">
                        <span class="font-semibold text-eg-ink" x-text="recipients"></span> {{ __('ext.msg.recipients') }}
                    </span>
                    <button type="button" class="eg-btn eg-btn--primary" onclick="return false">
                        <x-eg.icon name="send" class="h-4 w-4" />
                        {{ __('ext.msg.send') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Preview + recent --}}
        <div class="space-y-5 lg:col-span-2">
            <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
                <p class="mb-3 text-sm font-semibold text-eg-ink">{{ __('ext.msg.preview') }}</p>
                <div class="rounded-xl bg-eg-surface2 p-4">
                    <div class="mx-auto max-w-xs rounded-2xl border border-eg-border bg-eg-card p-3 shadow-eg-sm">
                        <div class="mb-2 flex items-center gap-2 border-b border-eg-border pb-2">
                            <span class="grid h-7 w-7 place-items-center rounded-full bg-eg-blue text-xs font-bold text-white">E</span>
                            <span class="text-xs font-semibold text-eg-ink">EduGate · {{ auth('merchant')->user()?->merchant?->name }}</span>
                        </div>
                        <p class="text-xs leading-relaxed text-eg-text" x-text="body"></p>
                    </div>
                </div>
            </div>

            <div class="rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
                <p class="border-b border-eg-border px-5 py-3 text-sm font-semibold text-eg-ink">{{ __('ext.msg.recent') }}</p>
                <div class="divide-y divide-eg-border/60">
                    @foreach ([['Payment deadline', '✈️ 📧', '312', '2h'], ['Missing payment', '📱', '47', '1d'], ['Payment received', '📧', '1', '2d']] as [$t, $ch, $n, $ago])
                        <div class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="text-sm font-medium text-eg-ink">{{ $t }}</p>
                                <p class="text-xs text-eg-muted">{{ $ch }} · {{ $n }} {{ __('ext.msg.recipients') }}</p>
                            </div>
                            <span class="text-xs text-eg-muted">{{ $ago }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
