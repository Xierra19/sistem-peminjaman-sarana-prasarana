<?php

namespace Tests\Unit;

use App\Services\PublicFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PublicFileStorageTest extends TestCase
{
    public function test_it_deletes_a_new_file_when_the_operation_fails(): void
    {
        Storage::fake('public');
        $service = app(PublicFileStorage::class);

        try {
            $service->runWithStoredFile(
                UploadedFile::fake()->create('attachment.pdf', 10, 'application/pdf'),
                'attachments',
                function (string $path): void {
                    Storage::disk('public')->assertExists($path);

                    throw new RuntimeException('Persistence failed.');
                },
            );

            $this->fail('Expected the operation to throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Persistence failed.', $exception->getMessage());
        }

        Storage::disk('public')->assertDirectoryEmpty('attachments');
    }

    public function test_it_keeps_a_new_file_when_the_operation_succeeds(): void
    {
        Storage::fake('public');
        $service = app(PublicFileStorage::class);

        $path = $service->runWithStoredFile(
            UploadedFile::fake()->create('attachment.pdf', 10, 'application/pdf'),
            'attachments',
            fn (string $storedPath): string => $storedPath,
        );

        Storage::disk('public')->assertExists($path);
    }
}
