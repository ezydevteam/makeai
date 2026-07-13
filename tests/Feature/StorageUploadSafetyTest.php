<?php

namespace Tests\Feature;

use App\Exceptions\StorageWriteException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class StorageUploadSafetyTest extends TestCase
{
    public function test_returns_relative_key_on_success(): void
    {
        Storage::fake('public');

        $path = store_public_upload(UploadedFile::fake()->image('a.jpg'), 'avatars');

        $this->assertStringStartsWith('avatars/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_deletes_old_file_only_after_successful_store(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.jpg', 'old-bytes');

        $new = store_public_upload(UploadedFile::fake()->image('new.jpg'), 'avatars', 'avatars/old.jpg');

        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing('avatars/old.jpg');
    }

    public function test_accepts_legacy_storage_prefixed_old_value(): void
    {
        config(['filesystems.disks.public.driver' => 'local']);
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.jpg', 'old-bytes');

        // Old value stored as a "/storage/..." URL (legacy format) must still resolve.
        $new = store_public_upload(UploadedFile::fake()->image('new.jpg'), 'avatars', '/storage/avatars/old.jpg');

        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing('avatars/old.jpg');
    }

    public function test_failed_write_throws_and_does_not_report_success(): void
    {
        // Simulate the throw=>false disk returning false on a failed write.
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('store')->once()->with('avatars', 'public')->andReturn(false);

        $this->expectException(StorageWriteException::class);

        store_public_upload($file, 'avatars');
    }

    public function test_failed_write_does_not_delete_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.jpg', 'keep-me');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('store')->once()->with('avatars', 'public')->andReturn(false);

        try {
            store_public_upload($file, 'avatars', 'avatars/old.jpg');
            $this->fail('Expected StorageWriteException');
        } catch (StorageWriteException) {
            // The old file must survive a failed upload (no data loss).
            Storage::disk('public')->assertExists('avatars/old.jpg');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
