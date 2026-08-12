<?php

namespace App\Crawler;

class AnalyzerResult
{
    /** @var array<string, mixed> */
    public array $data = [];

    /** @var IssueCode[] */
    public array $issues = [];

    public function add(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function issue(IssueCode $code): void
    {
        $this->issues[] = $code;
    }
}
