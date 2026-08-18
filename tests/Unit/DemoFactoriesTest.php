<?php

namespace Tests\Unit;

use App\Enums\PixelProvider;
use App\Http\Requests\QrCodeRequest;
use App\Models\Pixel;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\QrTemplate;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Reports\ReportTypeRegistry;
use Database\Factories\QrCodeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DemoFactoriesTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create(['name' => 'Demo', 'locked' => false]);
        $this->user = User::factory()->create();
    }

    public function test_all_four_factories_produce_persisted_records(): void
    {
        $this->assertNotNull(QrCode::factory()->forProject($this->project)->create()->id);
        $this->assertNotNull(QrTemplate::factory()->forProject($this->project)->create()->id);
        $this->assertNotNull(Pixel::factory()->forProject($this->project)->create()->id);
        $this->assertNotNull(ScheduledReport::factory()->forProject($this->project, $this->user)->create()->id);
    }

    /**
     * Eloquent's create()/make() only enforce schema constraints (NOT NULL,
     * FK, column type) — they never run QrCodeRequest's validation rules.
     * This proves QrCodeFactory::defaultStyle() is also application-valid,
     * so a future change to QrCodeRequest's style rules that silently
     * invalidates the hardcoded style is caught here instead of surfacing
     * inside the demo seeder several tasks later.
     */
    public function test_qr_code_default_style_passes_qr_code_request_style_rules(): void
    {
        $style = QrCodeFactory::defaultStyle();

        // is_dynamic defaults to false on a fresh, unbound request instance,
        // so the dynamic-QR branch (which needs 'project' input) never
        // executes and rules() returns the full static rule set, including
        // every style.* rule, without any extra scaffolding.
        $styleRules = collect((new QrCodeRequest)->rules())
            ->filter(fn ($rule, $key) => $key === 'style' || str_starts_with($key, 'style.'))
            ->all();

        $validator = Validator::make(['style' => $style], $styleRules);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    /**
     * Pixel::$casts maps 'provider' to the backed PixelProvider enum, so
     * Eloquent calls PixelProvider::from() while filling the attribute and
     * throws ValueError immediately for any string not in the enum. Making
     * the model (no DB round trip needed) is therefore already a real
     * assertion that the factory's hardcoded provider is a valid case.
     */
    public function test_pixel_factory_provider_is_a_real_provider_case(): void
    {
        $pixel = Pixel::factory()->forProject($this->project)->make();

        $this->assertInstanceOf(PixelProvider::class, $pixel->provider);
    }

    /**
     * ScheduledReport has no enum cast on 'type', so nothing catches a bad
     * value at the model layer. Assert directly against the registry that
     * actually resolves report types at send-time.
     */
    public function test_scheduled_report_factory_type_is_a_registered_report_type(): void
    {
        $report = ScheduledReport::factory()->forProject($this->project, $this->user)->make();

        $this->assertArrayHasKey($report->type, app(ReportTypeRegistry::class)->all());
    }
}
