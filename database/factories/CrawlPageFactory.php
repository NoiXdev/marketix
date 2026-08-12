<?php

namespace Database\Factories;

use App\Models\Crawl;
use App\Models\CrawlPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrawlPageFactory extends Factory
{
    protected $model = CrawlPage::class;

    public function definition(): array
    {
        return [
            'crawl_id' => Crawl::factory(),
            'url' => 'https://example.com/'.$this->faker->slug(),
            'status_code' => 200,
        ];
    }
}
