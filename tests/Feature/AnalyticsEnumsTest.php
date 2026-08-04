<?php

namespace Tests\Feature;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use Tests\TestCase;

class AnalyticsEnumsTest extends TestCase
{
    public function test_tracking_mode_has_labels_and_options(): void
    {
        $this->assertSame('cookieless', TrackingMode::Cookieless->value);
        $this->assertNotSame('', TrackingMode::Cookieless->label());
        $this->assertContains(
            ['value' => 'cookie', 'label' => TrackingMode::Cookie->label()],
            TrackingMode::options(),
        );
    }

    public function test_consent_mode_has_three_cases(): void
    {
        $this->assertCount(3, ConsentMode::cases());
        $this->assertSame('third_party_signal', ConsentMode::ThirdPartySignal->value);
        $this->assertNotSame('', ConsentMode::OwnBanner->label());
    }
}
