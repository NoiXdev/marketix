<?php

namespace Database\Factories;

use App\Models\Crawl;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrawlFactory extends Factory
{
    protected $model = Crawl::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'start_url' => 'https://example.com',
            'mode' => 'full_site',
            'status' => 'queued',
            'delay_ms' => 0,
            'pages_crawled' => 0,
        ];
    }
}
