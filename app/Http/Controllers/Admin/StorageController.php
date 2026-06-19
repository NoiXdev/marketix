<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorageSettingsRequest;
use App\Settings\StorageSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StorageController extends Controller
{
    public function edit(StorageSettings $settings)
    {
        return inertia('Admin/Storage/Edit', [
            'settings' => [
                'driver' => $settings->driver,
                's3_key' => $settings->s3_key,
                's3_region' => $settings->s3_region,
                's3_bucket' => $settings->s3_bucket,
                's3_endpoint' => $settings->s3_endpoint,
                's3_use_path_style' => $settings->s3_use_path_style,
            ],
            'has_s3_secret' => ! empty($settings->s3_secret),
        ]);
    }

    public function update(StorageSettingsRequest $request, StorageSettings $settings)
    {
        $data = $request->validated();

        $settings->driver = $data['driver'];
        $settings->s3_key = $data['s3_key'] ?? '';
        $settings->s3_region = $data['s3_region'] ?? '';
        $settings->s3_bucket = $data['s3_bucket'] ?? '';
        $settings->s3_endpoint = $data['s3_endpoint'] ?? '';
        $settings->s3_use_path_style = (bool) ($data['s3_use_path_style'] ?? false);

        // Only overwrite the secret when a new value is supplied (mask behaviour).
        if (! empty($data['s3_secret'])) {
            $settings->s3_secret = $data['s3_secret'];
        }

        $settings->save();

        return redirect()->route('app.admin.storage.edit')->with('success', 'Storage settings saved.');
    }

    public function test(StorageSettingsRequest $request)
    {
        $data = $request->validated();

        try {
            $disk = ($data['driver'] ?? 'local') === 's3'
                ? Storage::build([
                    'driver' => 's3',
                    'key' => $data['s3_key'] ?? '',
                    // Fall back to the stored secret when the form field is blank.
                    'secret' => $data['s3_secret'] ?: app(StorageSettings::class)->s3_secret,
                    'region' => $data['s3_region'] ?? '',
                    'bucket' => $data['s3_bucket'] ?? '',
                    'endpoint' => ($data['s3_endpoint'] ?? '') ?: null,
                    'use_path_style_endpoint' => (bool) ($data['s3_use_path_style'] ?? false),
                    'throw' => true,
                ])
                : Storage::disk();

            $path = 'storage-test-'.Str::uuid().'.txt';
            $disk->put($path, 'ok');
            $disk->get($path);
            $disk->delete($path);
        } catch (Throwable $e) {
            return back()->with('error', 'Storage test failed: '.$e->getMessage());
        }

        return back()->with('success', 'Storage connection OK.');
    }
}
