<?php

namespace App\Common\Command;

use Symfony\Component\Console\Exception\InvalidArgumentException;

class SetupValidator
{
    public static function nonEmptyString(mixed $input): mixed
    {
        if (empty($input))
        {
            throw new InvalidArgumentException('This value cannot be empty.');
        }

        return $input;
    }
}