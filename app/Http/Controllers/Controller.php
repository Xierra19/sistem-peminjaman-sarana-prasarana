<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Normalize a given name by trimming spaces and converting to lowercase without inner spaces.
     */
    protected function normalizeName(string $value): string
    {
        return strtolower(preg_replace('/\s+/', '', trim($value)));
    }
}
