<?php

namespace Tests\Feature;

use Tests\TestCase;

class InertiaPagePathTest extends TestCase
{
    /**
     * Inertia v3 ships `resources/js/pages` (lowercase) as its default page
     * path, while this project uses `resources/js/Pages`. A mismatch only
     * surfaces on a case-sensitive filesystem, so every `assertInertia()`
     * call would pass locally on macOS and fail in CI. Comparing against the
     * directory listing catches it on both.
     */
    public function test_configured_inertia_page_paths_match_the_directory_case(): void
    {
        $paths = config('inertia.pages.paths');

        $this->assertNotEmpty($paths, 'No Inertia page paths are configured.');

        foreach ($paths as $path) {
            $entries = scandir(dirname($path));

            $this->assertContains(
                basename($path),
                $entries,
                sprintf(
                    'Configured Inertia page path [%s] does not match any directory in [%s] with exact casing.',
                    $path,
                    dirname($path)
                )
            );
        }
    }

    public function test_inertia_view_finder_resolves_a_known_page_component(): void
    {
        $this->assertStringEndsWith(
            'resources/js/Pages/Team/Index.tsx',
            app('inertia.view-finder')->find('Team/Index')
        );
    }
}
