<?php

namespace App\Tests\Integration\Data\Common;

use App\Data\Common\RandomAccessPasswordGenerator;
use PHPUnit\Framework\TestCase;

final class RandomAccessPasswordGeneratorTest extends TestCase
{
    public function testGeneratesFiveLettersAndDigitsWithAtLeastOneOfEach(): void
    {
        $generator = new RandomAccessPasswordGenerator();

        for ($run = 0; $run < 200; ++$run) {
            $password = $generator->generate();

            self::assertMatchesRegularExpression('/^[A-HJ-NP-Za-hjkmnp-z2-9]{5}$/', $password);
            self::assertMatchesRegularExpression('/[A-Za-z]/', $password);
            self::assertMatchesRegularExpression('/[2-9]/', $password);
        }
    }
}
