<?php

declare(strict_types=1);

namespace Cnab\Tests;

use Cnab\CnabFile;
use Cnab\Exceptions\ParseException;
use Cnab\Layouts\GenericRemittance550;
use Cnab\Parser;
use Cnab\Record;
use Cnab\Writer;
use PHPUnit\Framework\TestCase;

final class RoundTripTest extends TestCase
{
    public function test_write_then_parse_preserves_values(): void
    {
        $layout = GenericRemittance550::layout();

        $file = new CnabFile($layout);
        $file->add((new Record($layout->record('0')))
            ->set('record_type', 0)
            ->set('remittance_literal', 'REMESSA')
            ->set('service_code', 1)
            ->set('company_code', 12345)
            ->set('company_name', 'ACME SECURITIES')
            ->set('bank_code', 341)
            ->set('bank_name', 'BANK')
            ->set('file_date', '180626')
            ->set('file_sequence', 7)
            ->set('record_sequence', 1));
        $file->add((new Record($layout->record('1')))
            ->set('record_type', 1)
            ->set('control_number', 'CTRL-0001')
            ->set('document_number', 'DOC123')
            ->set('due_date', '300626')
            ->set('amount', '2470.56')
            ->set('payer_doc_type', 2)
            ->set('payer_document', '12345678000199')
            ->set('payer_name', 'PAYER LTDA')
            ->set('payer_address', 'AV PAULISTA 1000')
            ->set('occurrence_code', 1)
            ->set('record_sequence', 2));
        $file->add((new Record($layout->record('9')))
            ->set('record_type', 9)
            ->set('record_sequence', 3));

        $raw = (new Writer)->write($file);

        // Every record must be exactly the line length plus the CRLF separator.
        foreach (explode("\r\n", rtrim($raw, "\r\n")) as $line) {
            $this->assertSame(GenericRemittance550::LINE_LENGTH, strlen($line));
        }

        $parsed = (new Parser)->parse($raw, $layout);

        $this->assertCount(3, $parsed->records());
        $this->assertSame('ACME SECURITIES', $parsed->header()->get('company_name'));

        $detail = $parsed->details()[0];
        $this->assertSame('2470.56', $detail->get('amount'));
        $this->assertSame('12345678000199', $detail->get('payer_document'));
        $this->assertSame('CTRL-0001', $detail->get('control_number'));
        $this->assertSame('300626', $detail->get('due_date')); // leading-zero date preserved
        $this->assertSame('000003', $parsed->trailer()->get('record_sequence'));
    }

    public function test_parses_stream_without_line_breaks(): void
    {
        $layout = GenericRemittance550::layout();

        $stream = str_repeat(' ', GenericRemittance550::LINE_LENGTH * 2);
        $stream[0] = '0';
        $stream[GenericRemittance550::LINE_LENGTH] = '9';

        $parsed = (new Parser)->parse($stream, $layout);

        $this->assertCount(2, $parsed->records());
        $this->assertSame('0', $parsed->header()->code());
        $this->assertSame('9', $parsed->trailer()->code());
    }

    public function test_rejects_line_with_wrong_length(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/expected 550/');

        (new Parser)->parse("0short\r\n", GenericRemittance550::layout());
    }

    public function test_rejects_unknown_record_type(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/unknown record type/');

        $line = '7'.str_repeat(' ', GenericRemittance550::LINE_LENGTH - 1);

        (new Parser)->parse($line, GenericRemittance550::layout());
    }
}
