<?php

declare(strict_types=1);

namespace Cnab\Tests\Support;

use Cnab\Exceptions\EncodingException;
use Cnab\Support\Decimal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    #[DataProvider('scaleCases')]
    public function test_to_scaled_int(string|int|float $value, int $scale, string $expected): void
    {
        $this->assertSame($expected, Decimal::toScaledInt($value, $scale));
    }

    /** @return iterable<string, array{string|int|float, int, string}> */
    public static function scaleCases(): iterable
    {
        yield 'decimal string' => ['2470.56', 2, '247056'];
        yield 'comma decimal' => ['2470,56', 2, '247056'];
        yield 'integer with scale' => [2470, 2, '247000'];
        yield 'float' => [2470.56, 2, '247056'];
        yield 'truncates extra decimals' => ['1.239', 2, '123'];
        yield 'pads missing decimals' => ['1.2', 2, '120'];
        yield 'zero' => ['0', 2, '0'];
        yield 'no scale' => ['12345', 0, '12345'];
    }

    public function test_from_scaled_int(): void
    {
        $this->assertSame('2470.56', Decimal::fromScaledInt('247056', 2));
        $this->assertSame('0.05', Decimal::fromScaledInt('000005', 2));
        $this->assertSame('12345', Decimal::fromScaledInt('0012345', 0));
        $this->assertSame('0.00', Decimal::fromScaledInt('000000', 2));
    }

    public function test_round_trips_large_values_without_float_loss(): void
    {
        $value = '99999999999.99';
        $scaled = Decimal::toScaledInt($value, 2);

        $this->assertSame('9999999999999', $scaled);
        $this->assertSame($value, Decimal::fromScaledInt($scaled, 2));
    }

    public function test_rejects_invalid_decimal(): void
    {
        $this->expectException(EncodingException::class);

        Decimal::toScaledInt('12.3.4', 2);
    }
}
