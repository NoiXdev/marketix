<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Settings\OperatorSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_store_operator_details(): void
    {
        $admin = User::factory()->create(['super_admin' => true]);

        $this->actingAs($admin)
            ->put(route('app.admin.operator.update'), [
                'agency_name' => '3B Agentur GmbH',
                'agency_address' => 'Hohenzollernring 1, 50672 Köln',
                'hosting_provider' => 'Hetzner Online GmbH',
                'hosting_location' => 'Nürnberg, Deutschland',
            ])
            ->assertRedirect();

        $settings = app(OperatorSettings::class);
        $this->assertSame('3B Agentur GmbH', $settings->agency_name);
        $this->assertSame('Hohenzollernring 1, 50672 Köln', $settings->agency_address);
        $this->assertSame('Hetzner Online GmbH', $settings->hosting_provider);
        $this->assertSame('Nürnberg, Deutschland', $settings->hosting_location);
    }

    public function test_a_normal_user_cannot_reach_the_operator_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.operator.edit'))
            ->assertForbidden();
    }

    public function test_blank_values_are_stored_as_null(): void
    {
        $admin = User::factory()->create(['super_admin' => true]);

        $settings = app(OperatorSettings::class);
        $settings->agency_name = 'Old Agency';
        $settings->agency_address = 'Old Address';
        $settings->hosting_provider = 'Old Host';
        $settings->hosting_location = 'Old Location';
        $settings->save();

        $this->actingAs($admin)
            ->put(route('app.admin.operator.update'), [
                'agency_name' => '',
                'agency_address' => '',
                'hosting_provider' => '',
                'hosting_location' => '',
            ])
            ->assertRedirect();

        $fresh = app(OperatorSettings::class);
        $this->assertNull($fresh->agency_name);
        $this->assertNull($fresh->agency_address);
        $this->assertNull($fresh->hosting_provider);
        $this->assertNull($fresh->hosting_location);
    }
}
