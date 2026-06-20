<?php

declare(strict_types=1);

namespace Cnab\Layouts;

use Cnab\Schema\Field;
use Cnab\Schema\Layout;
use Cnab\Schema\RecordDefinition;

/**
 * A generic 550-column collection ("cobrança") remittance layout.
 *
 * It demonstrates the canonical CNAB structure — header (0) / detail (1) /
 * trailer (9), a 6-digit trailing record sequence, numeric vs alphanumeric
 * fields and implied-decimal money — without reproducing any institution's
 * proprietary field set. Use it as a starting point and adapt to your bank's
 * actual manual.
 */
final class GenericRemittance550
{
    public const LINE_LENGTH = 550;

    public static function layout(): Layout
    {
        return new Layout(
            name: 'generic-remittance-550',
            lineLength: self::LINE_LENGTH,
            records: [
                self::header(),
                self::detail(),
                self::trailer(),
            ],
        );
    }

    private static function header(): RecordDefinition
    {
        return new RecordDefinition('0', self::LINE_LENGTH, name: 'Header', fields: [
            Field::numeric('record_type', 1, 1, description: 'Always 0'),
            Field::alpha('remittance_literal', 2, 8, 'Literal "REMESSA"'),
            Field::numeric('service_code', 10, 2, description: 'Service identifier'),
            Field::numeric('company_code', 12, 20, description: 'Assignor/company code'),
            Field::alpha('company_name', 32, 30, 'Company legal name'),
            Field::numeric('bank_code', 62, 3, description: 'Bank clearing number'),
            Field::alpha('bank_name', 65, 15, 'Bank name'),
            Field::numeric('file_date', 80, 6, description: 'Generation date DDMMYY'),
            Field::numeric('file_sequence', 86, 7, description: 'File sequence number'),
            Field::filler(93, 452),
            Field::numeric('record_sequence', 545, 6, description: 'Record number in file'),
        ]);
    }

    private static function detail(): RecordDefinition
    {
        return new RecordDefinition('1', self::LINE_LENGTH, name: 'Detail', fields: [
            Field::numeric('record_type', 1, 1, description: 'Always 1'),
            Field::alpha('control_number', 2, 25, 'Participant control number'),
            Field::alpha('document_number', 27, 10, 'Document/title number'),
            Field::numeric('due_date', 37, 6, description: 'Due date DDMMYY'),
            Field::numeric('amount', 43, 13, decimals: 2, description: 'Title face value'),
            Field::numeric('payer_doc_type', 56, 2, description: '01 CPF / 02 CNPJ'),
            Field::numeric('payer_document', 58, 14, description: 'Payer CPF/CNPJ'),
            Field::alpha('payer_name', 72, 40, 'Payer name'),
            Field::alpha('payer_address', 112, 40, 'Payer address'),
            Field::numeric('occurrence_code', 152, 2, description: 'Instruction/occurrence'),
            Field::filler(154, 391),
            Field::numeric('record_sequence', 545, 6, description: 'Record number in file'),
        ]);
    }

    private static function trailer(): RecordDefinition
    {
        return new RecordDefinition('9', self::LINE_LENGTH, name: 'Trailer', fields: [
            Field::numeric('record_type', 1, 1, description: 'Always 9'),
            Field::filler(2, 543),
            Field::numeric('record_sequence', 545, 6, description: 'Last record number'),
        ]);
    }
}
