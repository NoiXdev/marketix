<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesProject;
use App\Models\QrCode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the QR codes belonging to a project the caller has access to.')]
class ListQrCodesTool extends Tool
{
    use ResolvesProject;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'project' => ['required', 'string'],
        ]);

        $project = $this->resolveProject($request);

        if ($project === null) {
            return Response::error('Project not found or access denied.');
        }

        // Mirrors QrCodeController@index: scans come from the linked Url's
        // denormalized click counter, not from the QR code itself.
        $qrCodes = $project->qrCodes()
            ->with('url')
            ->latest()
            ->get()
            ->map(fn (QrCode $qrCode): array => [
                'id' => $qrCode->id,
                'name' => $qrCode->name,
                'type' => $qrCode->type,
                'is_dynamic' => $qrCode->is_dynamic,
                'scans' => $qrCode->url?->clicks ?? 0,
                'created_at' => $qrCode->created_at->toISOString(),
            ])
            ->values()
            ->all();

        return Response::text(json_encode($qrCodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('The project id or name to list QR codes for.')
                ->required(),
        ];
    }
}
