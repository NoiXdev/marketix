<?php

namespace App\Enums;

/**
 * Who the controller is for a generated privacy notice: the instance
 * operator measuring for their own company, or the agency's client, with
 * the agency acting as a processor on the client's instruction.
 */
enum ControllerRole: string
{
    case OwnCompany = 'own_company';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::OwnCompany => __('privacy_notice.roles.own_company'),
            self::Client => __('privacy_notice.roles.client'),
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
