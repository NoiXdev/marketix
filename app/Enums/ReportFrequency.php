<?php

namespace App\Enums;

enum ReportFrequency: string
{
    case Off = 'off';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    public function isSendable(): bool
    {
        return $this !== self::Off;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()],
            self::cases()
        );
    }
}
