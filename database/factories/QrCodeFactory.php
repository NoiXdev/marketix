<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\QrCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QrCode> */
class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'type' => 'link',
            'is_dynamic' => false,
            'content' => ['url' => 'https://example.com/'.$this->faker->slug()],
            'style' => self::defaultStyle(),
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultStyle(): array
    {
        return [
            'foreground' => '#111827',
            'background' => '#ffffff',
            'logo_type' => 'none',
            'logo_name' => null,
            'logo_data' => null,
            'logo_size' => 20,
            'module_mode' => 'square',
            'module_rounding' => 0,
            'eye_frame_mode' => 'square',
            'eye_frame_rounding' => 0,
            'eye_ball_mode' => 'square',
            'eye_ball_rounding' => 0,
            'eye_color' => null,
            'error_correction' => 'M',
            'quiet_zone' => 4,
            'logo_margin' => 2,
            'logo_clear_modules' => true,
            'frame_style' => 'none',
            'frame_text' => null,
            'frame_text_color' => '#111827',
            'frame_color' => '#111827',
            'frame_background' => '#ffffff',
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => ['project_id' => $project->id]);
    }
}
