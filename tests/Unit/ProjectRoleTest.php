<?php

namespace Tests\Unit;

use App\Enums\ProjectRole;
use PHPUnit\Framework\TestCase;

class ProjectRoleTest extends TestCase
{
    public function test_values(): void
    {
        $this->assertSame('admin', ProjectRole::Admin->value);
        $this->assertSame('member', ProjectRole::Member->value);
    }

    public function test_labels(): void
    {
        $this->assertSame('Admin', ProjectRole::Admin->label());
        $this->assertSame('Member', ProjectRole::Member->label());
    }

    public function test_options_shape(): void
    {
        $options = ProjectRole::options();
        $this->assertSame(
            [['value' => 'admin', 'label' => 'Admin'], ['value' => 'member', 'label' => 'Member']],
            $options
        );
    }
}
