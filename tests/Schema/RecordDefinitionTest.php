<?php

declare(strict_types=1);

namespace Cnab\Tests\Schema;

use Cnab\Exceptions\LayoutException;
use Cnab\Schema\Field;
use Cnab\Schema\RecordDefinition;
use PHPUnit\Framework\TestCase;

final class RecordDefinitionTest extends TestCase
{
    public function test_accepts_contiguous_fields_that_tile_the_line(): void
    {
        $record = new RecordDefinition('0', 10, [
            Field::numeric('a', 1, 4),
            Field::alpha('b', 5, 6),
        ]);

        $this->assertTrue($record->has('a'));
        $this->assertSame(6, $record->field('b')->length);
    }

    public function test_rejects_gap_between_fields(): void
    {
        $this->expectException(LayoutException::class);
        $this->expectExceptionMessageMatches('/gap or overlap/');

        new RecordDefinition('0', 10, [
            Field::numeric('a', 1, 4),
            Field::alpha('b', 6, 5),
        ]);
    }

    public function test_rejects_overlap_between_fields(): void
    {
        $this->expectException(LayoutException::class);

        new RecordDefinition('0', 10, [
            Field::numeric('a', 1, 5),
            Field::alpha('b', 4, 6),
        ]);
    }

    public function test_rejects_wrong_total_width(): void
    {
        $this->expectException(LayoutException::class);
        $this->expectExceptionMessageMatches('/covers 8 columns but the layout line length is 10/');

        new RecordDefinition('0', 10, [
            Field::numeric('a', 1, 4),
            Field::alpha('b', 5, 4),
        ]);
    }

    public function test_rejects_duplicate_field_names(): void
    {
        $this->expectException(LayoutException::class);

        new RecordDefinition('0', 8, [
            Field::numeric('a', 1, 4),
            Field::numeric('a', 5, 4),
        ]);
    }
}
