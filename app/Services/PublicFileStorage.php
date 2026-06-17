<?php

namespace App\Services;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublicFileStorage
{
    public const DISK = 'local';

    public function runWithStoredFile(
        ?UploadedFile $file,
        string $directory,
        Closure $operation,
    ): mixed {
        $path = $file?->store($directory, self::DISK);

        try {
            return $operation($path);
        } catch (Throwable $exception) {
            $this->delete($path);

            throw $exception;
        }
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
