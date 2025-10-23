<?php

namespace App\DataTransferObjects\Semesters;

class PreviewRow
{
    /** @param array<string,mixed> $normalizedData */
    public function __construct(
        public SemesterDefaultImportRow $raw,
        public array $normalizedData,
        public array $errors = []
    ) {
    }

    public function status(): string
    {
        return empty($this->errors) ? 'OK' : 'ERROR';
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }
}