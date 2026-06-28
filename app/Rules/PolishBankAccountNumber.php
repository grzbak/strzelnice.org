<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PolishBankAccountNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{26}$/', $value)) {
            $fail('Numer rachunku musi zawierać 26 cyfr.');

            return;
        }

        $remainder = 0;

        $ibanCheckValue = substr($value, 2).'2521'.substr($value, 0, 2);

        foreach (str_split($ibanCheckValue) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        if ($remainder !== 1) {
            $fail('Numer rachunku bankowego ma nieprawidłową sumę kontrolną.');
        }
    }
}
