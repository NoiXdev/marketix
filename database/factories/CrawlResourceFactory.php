<?php

namespace Database\Factories;

use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Models\CrawlResource;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrawlResourceFactory extends Factory
{
    protected $model = CrawlResource::class;

    public function definition(): array
    {
        return [
            'crawl_id' => Crawl::factory(),
            'from_page_id' => CrawlPage::factory(),
            'url' => 'https://example.com/assets/'.$this->faker->slug().'.js',
            'type' => 'javascript',
            'is_internal' => true,
        ];
    }
}
