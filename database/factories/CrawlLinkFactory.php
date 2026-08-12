<?php

namespace Database\Factories;

use App\Models\Crawl;
use App\Models\CrawlLink;
use App\Models\CrawlPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrawlLinkFactory extends Factory
{
    protected $model = CrawlLink::class;

    public function definition(): array
    {
        return [
            'crawl_id' => Crawl::factory(),
            'from_page_id' => CrawlPage::factory(),
            'to_url' => 'https://example.com/'.$this->faker->slug(),
            'type' => 'internal',
        ];
    }
}
