<?php

namespace Tests\Unit;

use App\Support\VisitorHash;
use Tests\TestCase;

class VisitorHashTest extends TestCase
{
    public function test_same_inputs_same_day_produce_same_hash(): void
    {
        $a = VisitorHash::for('203.0.113.9', 'UA/1.0');
        $b = VisitorHash::for('203.0.113.9', 'UA/1.0');

        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
    }

    public function test_different_ip_produces_different_hash(): void
    {
        $this->assertNotSame(
            VisitorHash::for('203.0.113.9', 'UA/1.0'),
            VisitorHash::for('198.51.100.7', 'UA/1.0'),
        );
    }

    public function test_different_user_agent_produces_different_hash(): void
    {
        $this->assertNotSame(
            VisitorHash::for('203.0.113.9', 'UA/1.0'),
            VisitorHash::for('203.0.113.9', 'UA/2.0'),
        );
    }

    public function test_salt_rotates_across_days(): void
    {
        $today = VisitorHash::for('203.0.113.9', 'UA/1.0');
        $this->travel(2)->days();

        $this->assertNotSame($today, VisitorHash::for('203.0.113.9', 'UA/1.0'));
    }

    public function test_null_ip_is_accepted(): void
    {
        $this->assertSame(64, strlen(VisitorHash::for(null, 'UA/1.0')));
    }
}
