<?php

namespace App\Support\Widgets;

use App\Enums\WidgetType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WidgetSanitizer
{
    public function __construct(private WidgetRegistry $registry) {}

    /**
     * @param  array<int, mixed>  $widgets
     * @return array<int, array<string, mixed>>
     */
    public function sanitize(array $widgets): array
    {
        $out = [];
        foreach ($widgets as $w) {
            if (! is_array($w) || ! isset($w['id'], $w['type'], $w['config'], $w['layout'])) {
                throw ValidationException::withMessages(['widgets' => 'Malformed widget.']);
            }
            $type = WidgetType::tryFrom((string) $w['type']);
            if ($type === null) {
                throw ValidationException::withMessages(['widgets' => "Unknown widget type: {$w['type']}"]);
            }

            $config = Validator::make((array) $w['config'], $this->registry->configRules($type))->validate();
            $out[] = ['id' => (string) $w['id'], 'type' => $type->value, 'config' => $config, 'layout' => $this->clampLayout($type, (array) $w['layout'])];
        }

        return $out;
    }

    /** @return array{x:int,y:int,w:int,h:int} */
    private function clampLayout(WidgetType $type, array $layout): array
    {
        $b = $this->registry->layoutBounds($type);
        $w = max($b['minW'], min(12, (int) ($layout['w'] ?? $b['defaultW'])));
        $h = max($b['minH'], min($b['maxH'], (int) ($layout['h'] ?? $b['defaultH'])));
        $x = max(0, min(12 - $w, (int) ($layout['x'] ?? 0)));
        $y = max(0, (int) ($layout['y'] ?? 0));

        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }
}
