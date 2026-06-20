<?php

declare(strict_types=1);

namespace Cnab\Tests;

use Cnab\CnabFile;
use Cnab\Layouts\Cnab240Cobranca;
use Cnab\Parser;
use Cnab\Record;
use Cnab\Writer;
use PHPUnit\Framework\TestCase;

final class Cnab240CobrancaTest extends TestCase
{
    public function test_layout_constructs_and_every_record_tiles_240(): void
    {
        // Construction throws if any record does not cover columns 1..240.
        $layout = Cnab240Cobranca::layout();

        $this->assertSame(240, $layout->lineLength);
        foreach (['0', '1', '3P', '3Q', '5', '9'] as $code) {
            $this->assertTrue($layout->hasRecord($code), "missing record $code");
        }
    }

    public function test_segment_routing_round_trips(): void
    {
        $layout = Cnab240Cobranca::layout();
        $file = new CnabFile($layout);

        $file->add((new Record($layout->record('0')))->set('record_type', 0)->set('bank_code', 1));
        $file->add((new Record($layout->record('1')))->set('record_type', 1)->set('lot', 1));
        $file->add((new Record($layout->record('3P')))
            ->set('record_type', 3)
            ->set('segment', 'P')
            ->set('lot', 1)
            ->set('record_number', 1)
            ->set('document_number', 'DOC0001')
            ->set('due_date', '30062026')
            ->set('nominal_value', '2470.56'));
        $file->add((new Record($layout->record('3Q')))
            ->set('record_type', 3)
            ->set('segment', 'Q')
            ->set('lot', 1)
            ->set('record_number', 2)
            ->set('payer_doc_type', 1)
            ->set('payer_document', '52998224725')
            ->set('payer_name', 'MARIA DE SOUZA'));
        $file->add((new Record($layout->record('5')))->set('record_type', 5)->set('lot', 1)->set('lot_record_count', 4));
        $file->add((new Record($layout->record('9')))->set('record_type', 9)->set('lot', 9999)->set('record_count', 6));

        $parsed = (new Parser)->parse((new Writer)->write($file), $layout);

        $this->assertCount(6, $parsed->records());

        // detailCode is "3P": one detail per título.
        $this->assertCount(1, $parsed->details());
        $p = $parsed->details()[0];
        $this->assertSame('P', $p->get('segment'));
        $this->assertSame('2470.56', $p->get('nominal_value'));
        $this->assertSame('30062026', $p->get('due_date'));

        // The Q segment is routed to its own definition.
        $q = $parsed->records()[3];
        $this->assertSame('3Q', $q->code());
        $this->assertSame('MARIA DE SOUZA', $q->get('payer_name'));

        $this->assertSame('0', $parsed->header()->get('record_type'));
        $this->assertSame('9', $parsed->trailer()->get('record_type'));
    }
}
