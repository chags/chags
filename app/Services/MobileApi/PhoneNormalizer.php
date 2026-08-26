<?php

namespace App\Services\MobileApi;

use InvalidArgumentException;

class PhoneNormalizer
{
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (in_array(strlen($digits), [10, 11], true)) {
            $digits = '55'.$digits;
        }

        if (! preg_match('/^[1-9]\d{9,14}$/', $digits)) {
            throw new InvalidArgumentException('Informe um telefone válido no formato internacional.');
        }

        return '+'.$digits;
    }
}
