<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a contact point at an institution is for.
 *
 * Institutions do not have one phone number — a parent chasing a payment needs
 * the accounting desk, a student needs the registrar, and neither should reach
 * the rector's office. The kind is what lets us show the right one in the right
 * place instead of a single line labelled "phone".
 */
enum MerchantContactKind: string
{
    case Accounting = 'accounting';       // buxgalteriya
    case Support = 'support';
    case StudentAffairs = 'student_affairs';
    case Admissions = 'admissions';
    case Management = 'management';
    case Other = 'other';

    public function label(): string
    {
        return __('cabinet.contacts.kind_'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Accounting => 'wallet',
            self::Support => 'megaphone',
            self::StudentAffairs, self::Admissions => 'users',
            self::Management => 'building',
            self::Other => 'document',
        };
    }

    /** @return array<string, string> value => translated label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
