<?php

declare(strict_types=1);

namespace Cnab\Tests;

use Cnab\Exceptions\CnabException;
use Cnab\LayoutRegistry;
use Cnab\Layouts\GenericRemittance550;
use PHPUnit\Framework\TestCase;

final class LayoutRegistryTest extends TestCase
{
    public function test_registers_and_resolves_layouts(): void
    {
        $registry = (new LayoutRegistry)
            ->register('generic-550', GenericRemittance550::layout(), 'Generic 550');

        $this->assertTrue($registry->has('generic-550'));
        $this->assertSame(['generic-550'], $registry->ids());
        $this->assertSame(550, $registry->get('generic-550')->lineLength);
    }

    public function test_describe_exposes_family_from_line_length(): void
    {
        $registry = (new LayoutRegistry)
            ->register('generic-550', GenericRemittance550::layout(), 'Generic 550');

        $this->assertSame([
            ['id' => 'generic-550', 'label' => 'Generic 550', 'lineLength' => 550, 'family' => 'CNAB550'],
        ], $registry->describe());
    }

    public function test_unknown_layout_throws(): void
    {
        $this->expectException(CnabException::class);

        (new LayoutRegistry)->get('nope');
    }
}
