<?php

namespace Tests\Feature;

use App\Services\CloudStorageService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    private function useCloud(): void
    {
        $svc = new CloudStorageService('r2', 'k', 's', 'auto', 'bucket', 'https://ep.example.com', 'https://cdn.example.com');
        config(['filesystems.disks.public' => $svc->diskConfig()]);
        Storage::forgetDisk('public');
    }

    public function test_local_driver_returns_root_relative(): void
    {
        config(['filesystems.disks.public.driver' => 'local']);
        Storage::forgetDisk('public');

        $this->assertSame('/storage/blog/x.jpg', media_url('blog/x.jpg'));
        $this->assertSame('/storage/blog/x.jpg', media_url('/storage/blog/x.jpg')); // legacy prefix not doubled
        $this->assertSame('', media_url(''));
        $this->assertSame('', media_url(null));
    }

    public function test_cloud_driver_returns_bucket_url(): void
    {
        $this->useCloud();

        $this->assertSame('https://cdn.example.com/blog/x.jpg', media_url('blog/x.jpg'));
        // legacy value with /storage/ baked in must still resolve to the bucket
        $this->assertSame('https://cdn.example.com/blog/x.jpg', media_url('/storage/blog/x.jpg'));
    }

    public function test_absolute_urls_and_data_uris_pass_through(): void
    {
        $this->useCloud();
        $this->assertSame('https://other.com/a.png', media_url('https://other.com/a.png'));
        $this->assertSame('//cdn.io/a.png', media_url('//cdn.io/a.png'));
        $this->assertSame('data:image/png;base64,AAAA', media_url('data:image/png;base64,AAAA'));
    }

    public function test_media_path_reduces_to_relative_key(): void
    {
        $this->useCloud();
        $this->assertSame('blog/x.jpg', media_path('https://cdn.example.com/blog/x.jpg'));
        $this->assertSame('blog/x.jpg', media_path('/storage/blog/x.jpg'));
        $this->assertSame('blog/x.jpg', media_path('blog/x.jpg'));
        $this->assertSame('', media_path(''));
    }

    public function test_media_path_local(): void
    {
        config(['filesystems.disks.public.driver' => 'local']);
        Storage::forgetDisk('public');
        $this->assertSame('avatars/1.png', media_path('/storage/avatars/1.png'));
        $this->assertSame('avatars/1.png', media_path('avatars/1.png'));
    }

    /**
     * The `mediaBase` Inertia prop is the contract the whole Vue layer relies on
     * (resources/js/lib/media.ts prepends it to every stored media key), so both
     * driver cases are pinned here.
     */
    public function test_media_base_prop_is_local_storage_prefix(): void
    {
        config(['filesystems.disks.public.driver' => 'local']);
        Storage::forgetDisk('public');

        $this->assertSame('/storage', $this->mediaBaseProp());
    }

    public function test_media_base_prop_is_bucket_origin_on_cloud(): void
    {
        $this->useCloud();

        $this->assertSame('https://cdn.example.com', $this->mediaBaseProp());
    }

    private function mediaBaseProp(): string
    {
        $middleware = new \App\Http\Middleware\HandleInertiaRequests;

        return $middleware->share(\Illuminate\Http\Request::create('/'))['mediaBase'];
    }
}
