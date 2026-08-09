<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables carrying a `style` JSON column that predates the v2 schema.
     *
     * @var list<string>
     */
    private array $tables = ['qr_codes', 'qr_code_versions'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::table($table)
                ->select(['id', 'style'])
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        $style = json_decode((string) $row->style, true);

                        if (! $this->isOldShape($style)) {
                            // Not an array, or already migrated (has module_mode),
                            // or never had the old shape to begin with — skip.
                            // This is what makes the migration idempotent.
                            continue;
                        }

                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['style' => json_encode($this->rewrite($style))]);
                    }
                }, 'id');
        }
    }

    /**
     * Forward-only. The old keys (dot_style/corner_square_style/
     * corner_dot_style) are deliberately retained alongside the new v2
     * keys added by up(), so no data is destroyed by this migration — but
     * once new-schema rows exist we can no longer distinguish "migrated
     * from old shape" from "always had the new shape", so there is no
     * reliable inverse transform. Rolling back is a documented no-op.
     */
    public function down(): void
    {
        // Intentionally empty — see docblock above.
    }

    /**
     * Old-shape detection: has the legacy `dot_style` key but not yet the
     * new `module_mode` key. Rows that are already new-shape (or that have
     * neither, e.g. malformed/empty style) are left untouched.
     */
    private function isOldShape(mixed $style): bool
    {
        return is_array($style)
            && array_key_exists('dot_style', $style)
            && ! array_key_exists('module_mode', $style);
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private function rewrite(array $style): array
    {
        [$moduleMode, $moduleRounding] = $this->mapDotStyle((string) ($style['dot_style'] ?? 'square'));
        [$eyeFrameMode, $eyeFrameRounding] = $this->mapCornerSquareStyle((string) ($style['corner_square_style'] ?? 'square'));
        [$eyeBallMode, $eyeBallRounding] = $this->mapCornerDotStyle((string) ($style['corner_dot_style'] ?? 'square'));

        // Merge onto the existing row so foreground/background/logo_* and
        // the old keys are carried over unchanged; only the new v2 keys
        // are added/overwritten.
        return array_merge($style, [
            'module_mode' => $moduleMode,
            'module_rounding' => $moduleRounding,
            'eye_frame_mode' => $eyeFrameMode,
            'eye_frame_rounding' => $eyeFrameRounding,
            'eye_ball_mode' => $eyeBallMode,
            'eye_ball_rounding' => $eyeBallRounding,
            'eye_color' => $style['eye_color'] ?? null,
            'error_correction' => 'Q',
            'quiet_zone' => 4,
            'logo_margin' => 1,
            'logo_clear_modules' => true,
            'frame_style' => 'none',
            'frame_text' => '',
            'frame_text_color' => '#000000',
            'frame_color' => '#000000',
            'frame_background' => '#ffffff',
        ]);
    }

    /**
     * @return array{0: string, 1: float|int}
     */
    private function mapDotStyle(string $value): array
    {
        return match ($value) {
            'dots' => ['dots', 1],
            'rounded' => ['rounded', 0.5],
            'extra-rounded' => ['rounded', 1],
            'classy' => ['classy', 0.5],
            'classy-rounded' => ['classy', 1],
            default => ['square', 0], // 'square' and any unknown value.
        };
    }

    /**
     * @return array{0: string, 1: float|int}
     */
    private function mapCornerSquareStyle(string $value): array
    {
        return match ($value) {
            'extra-rounded' => ['rounded', 1],
            'dot' => ['dots', 1],
            default => ['square', 0], // 'square' and any unknown value.
        };
    }

    /**
     * @return array{0: string, 1: float|int}
     */
    private function mapCornerDotStyle(string $value): array
    {
        return match ($value) {
            'dot' => ['dot', 1],
            default => ['square', 0], // 'square' and any unknown value.
        };
    }
};
