<?php

declare(strict_types=1);

namespace Cnab\Tests\Formatting;

use Cnab\Exceptions\EncodingException;
use Cnab\Formatting\FieldCodec;
use Cnab\Schema\Field;
use PHPUnit\Framework\TestCase;

final class FieldCodecTest extends TestCase
{
    private FieldCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new FieldCodec;
    }

    public function test_encodes_numeric_right_aligned_zero_padded(): void
    {
        $field = Field::numeric('seq', 1, 6);

        $this->assertSame('000123', $this->codec->encode($field, 123));
    }

    public function test_encodes_alpha_left_aligned_space_padded(): void
    {
        $field = Field::alpha('name', 1, 10);

        $this->assertSame('ACME      ', $this->codec->encode($field, 'ACME'));
    }

    public function test_encodes_money_with_implied_decimals(): void
    {
        $field = Field::numeric('amount', 1, 13, decimals: 2);

        $this->assertSame('0000000247056', $this->codec->encode($field, '2470.56'));
    }

    public function test_decodes_money_back_to_decimal_string(): void
    {
        $field = Field::numeric('amount', 1, 13, decimals: 2);

        $this->assertSame('2470.56', $this->codec->decode($field, '0000000247056'));
    }

    public function test_decodes_plain_numeric_preserving_leading_zeros(): void
    {
        $field = Field::numeric('due_date', 1, 6);

        $this->assertSame('010826', $this->codec->decode($field, '010826'));
    }

    public function test_decodes_alpha_trimming_trailing_spaces(): void
    {
        $field = Field::alpha('name', 1, 10);

        $this->assertSame('ACME', $this->codec->decode($field, 'ACME      '));
    }

    public function test_uses_default_when_value_is_null(): void
    {
        $field = new Field('lit', 1, 7, default: 'REMESSA');

        $this->assertSame('REMESSA', $this->codec->encode($field, null));
    }

    public function test_rejects_value_that_does_not_fit(): void
    {
        $this->expectException(EncodingException::class);

        $this->codec->encode(Field::numeric('seq', 1, 3), 12345);
    }

    public function test_rejects_negative_numeric(): void
    {
        $this->expectException(EncodingException::class);

        $this->codec->encode(Field::numeric('amount', 1, 10, decimals: 2), '-1.00');
    }
}
