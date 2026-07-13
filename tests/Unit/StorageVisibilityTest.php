<?php

namespace Tests\Unit;

use App\Services\CloudStorageService;
use PHPUnit\Framework\TestCase;

class StorageVisibilityTest extends TestCase
{
    private function cfg(string $driver): array
    {
        return (new CloudStorageService(
            driver: $driver,
            accessKey: 'k', secretKey: 's', region: 'auto',
            bucket: 'b', endpoint: 'https://ep.example.com', url: 'https://cdn.example.com'
        ))->diskConfig();
    }

    public function test_acl_capable_drivers_send_public_visibility(): void
    {
        foreach (['s3', 'spaces', 'wasabi'] as $d) {
            $c = $this->cfg($d);
            $this->assertSame('public', $c['visibility'] ?? null, "$d should set public visibility");
            $this->assertArrayNotHasKey('retain_visibility', $c);
        }
    }

    public function test_r2_and_b2_omit_acl(): void
    {
        foreach (['r2', 'b2'] as $d) {
            $c = $this->cfg($d);
            $this->assertArrayNotHasKey('visibility', $c, "$d must NOT send an ACL");
            $this->assertFalse($c['retain_visibility'] ?? null, "$d should set retain_visibility=false");
        }
    }

    public function test_core_s3_config_shape_intact(): void
    {
        $c = $this->cfg('r2');
        $this->assertSame('s3', $c['driver']);
        $this->assertTrue($c['use_path_style_endpoint']);
        $this->assertSame('https://cdn.example.com', $c['url']);
    }
}
