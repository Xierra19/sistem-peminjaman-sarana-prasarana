<?php

namespace App\DataTransferObjects\Semesters;

class PreviewResult
{
    /**
     * @param PreviewRow[] $rows
     */
    public function __construct(
        public array $rows
    ) {
    }

    public function hasErrors(): bool
    {
        foreach ($this->rows as $row) {
            if ($row->hasErrors()) {
                return true;
            }
        }

        return false;
    }
}