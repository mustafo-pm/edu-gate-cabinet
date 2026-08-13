<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which legal document this is, and therefore who it binds.
 *
 * EduGate stands between three counterparties with three different
 * relationships, so one "offer" cannot cover them: a parent accepts by paying
 * and never sees a cabinet, an institution signs a bilateral contract with
 * commission rates in it, and a PSP agrees to technical obligations besides.
 * The type is what decides whose acceptance we have to record.
 */
enum LegalDocumentType: string
{
    /** Public offer to payers — accepted by paying, never signed. */
    case PublicOffer = 'public_offer';

    case PrivacyPolicy = 'privacy_policy';

    /** Institution (merchant) terms, including the data-processing annex. */
    case InstitutionAgreement = 'institution_agreement';

    case PspAgreement = 'psp_agreement';

    case RefundPolicy = 'refund_policy';

    public function label(): string
    {
        return match ($this) {
            self::PublicOffer => 'Public offer (payers)',
            self::PrivacyPolicy => 'Privacy policy',
            self::InstitutionAgreement => 'Institution agreement',
            self::PspAgreement => 'PSP agreement',
            self::RefundPolicy => 'Refund and dispute policy',
        };
    }

    /**
     * Whose acceptance this document expects.
     *
     * `null` means nobody accepts it explicitly — a privacy policy is published
     * and disclosed, not agreed to, and recording a tick-box against it would
     * misrepresent what happened.
     */
    public function acceptedBy(): ?string
    {
        return match ($this) {
            self::PublicOffer, self::RefundPolicy => 'payer',
            self::InstitutionAgreement => 'merchant',
            self::PspAgreement => 'psp',
            self::PrivacyPolicy => null,
        };
    }

    /** @return array<string, string> value => label, for Filament selects. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
