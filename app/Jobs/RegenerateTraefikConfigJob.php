<?php

namespace App\Jobs;

use App\Models\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Yaml\Yaml;

class RegenerateTraefikConfigJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $domains = Domain::all()->pluck('name');

        $config = ['http' => ['routers' => [], 'services' => []]];

        foreach ($domains as $domain) {
            $key = $this->domainToKey($domain);

            $config['http']['routers'][$key] = [
                'rule' => "Host(`{$domain}`)",
                'entrypoints' => ['websecure'],
                'service' => 'laravel-app',
                'tls' => ['certResolver' => 'letsencrypt'],
            ];
        }

        // Central service always points at the Laravel/Octane container.
        $config['http']['services']['laravel-app'] = [
            'loadBalancer' => [
                'servers' => [['url' => config('services.traefik.app_url')]],
            ],
        ];

        $path = config('services.traefik.dynamic_file');

        @mkdir(dirname($path), 0755, true);

        file_put_contents($path, Yaml::dump($config, 6, 2));
    }

    private function domainToKey(string $domain): string
    {
        return 'custom-'.preg_replace('/[^a-zA-Z0-9]/', '-', $domain);
    }
}
