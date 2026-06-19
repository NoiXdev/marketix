<?php

namespace Tests\Unit;

use App\Enums\ReportFrequency;
use PHPUnit\Framework\TestCase;

class ReportFrequencyTest extends TestCase
{
    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Off', ReportFrequency::Off->label());
        $this->assertSame('Daily', ReportFrequency::Daily->label());
        $this->assertSame('Weekly', ReportFrequency::Weekly->label());
        $this->assertSame('Monthly', ReportFrequency::Monthly->label());
    }

    public function test_only_off_is_not_sendable(): void
    {
        $this->assertFalse(ReportFrequency::Off->isSendable());
        $this->assertTrue(ReportFrequency::Daily->isSendable());
        $this->assertTrue(ReportFrequency::Weekly->isSendable());
        $this->assertTrue(ReportFrequency::Monthly->isSendable());
    }

    public function test_options_lists_every_case(): void
    {
        $options = ReportFrequency::options();
        $this->assertCount(4, $options);
        $this->assertSame(['value' => 'off', 'label' => 'Off'], $options[0]);
        $this->assertSame(['value' => 'monthly', 'label' => 'Monthly'], $options[3]);
    }
}
