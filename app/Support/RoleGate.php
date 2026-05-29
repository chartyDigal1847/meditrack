<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleGate
{
    public static function role(Request $request): string
    {
        return (string) $request->attributes->get('meditrack_role', 'student');
    }

    public static function nurse(Request $request): void
    {
        if (self::role($request) !== 'nurse') {
            throw ValidationException::withMessages(['role' => 'Only nurses can modify clinical medical data.'])->status(403);
        }
    }
}
