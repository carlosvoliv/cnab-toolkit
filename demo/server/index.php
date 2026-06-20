<?php

declare(strict_types=1);

/**
 * Tiny demo backend for cnab-toolkit.
 *
 * It is NOT part of the library — just a thin HTTP surface so the Vue demo can
 * exercise the real Writer/Parser. Run it with PHP's built-in server:
 *
 *   php -S 127.0.0.1:8000 -t demo/server
 *
 * The star of the demo is /api/generate: structured input -> a valid CNAB
 * remittance file (which is what a securitization back office actually does).
 */

use Cnab\CnabFile;
use Cnab\Exceptions\CnabException;
use Cnab\Layouts\GenericRemittance550;
use Cnab\Parser;
use Cnab\Record;
use Cnab\Schema\RecordDefinition;
use Cnab\Support\Decimal;
use Cnab\Writer;

require __DIR__.'/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$layout = GenericRemittance550::layout();

try {
    match (true) {
        $method === 'POST' && $path === '/api/generate' => json(generate($layout, jsonBody())),
        default => fail(404, 'Not found.'),
    };
} catch (CnabException $e) {
    fail(422, $e->getMessage());
} catch (Throwable $e) {
    fail(500, $e->getMessage());
}

/**
 * Build a remittance file from structured input, then parse it back so the UI
 * can show both the raw fixed-width output and the decoded records as proof.
 *
 * @param  array<string, mixed>  $input
 */
function generate($layout, array $input): array
{
    $header = is_array($input['header'] ?? null) ? $input['header'] : [];
    $titles = is_array($input['titles'] ?? null) ? array_values($input['titles']) : [];

    if ($titles === []) {
        throw new CnabException('Adicione ao menos um título à remessa.');
    }

    $file = new CnabFile($layout);
    $seq = 1;

    $file->add((new Record($layout->record('0')))
        ->set('record_type', 0)
        ->set('remittance_literal', 'REMESSA')
        ->set('service_code', (int) ($header['service_code'] ?? 1))
        ->set('company_code', digits($header['company_code'] ?? '0'))
        ->set('company_name', upper($header['company_name'] ?? ''))
        ->set('bank_code', digits($header['bank_code'] ?? '0'))
        ->set('bank_name', upper($header['bank_name'] ?? ''))
        ->set('file_date', date('dmy'))
        ->set('file_sequence', digits($header['file_sequence'] ?? '1'))
        ->set('record_sequence', $seq++));

    foreach ($titles as $i => $title) {
        if (! is_array($title)) {
            continue;
        }

        $file->add((new Record($layout->record('1')))
            ->set('record_type', 1)
            ->set('control_number', upper($title['control_number'] ?? sprintf('CTRL-%04d', $i + 1)))
            ->set('document_number', upper($title['document_number'] ?? ''))
            ->set('due_date', toDdMmYy($title['due_date'] ?? ''))
            ->set('amount', sanitizeAmount($title['amount'] ?? '0'))
            ->set('payer_doc_type', (int) ($title['payer_doc_type'] ?? 1))
            ->set('payer_document', digits($title['payer_document'] ?? '0'))
            ->set('payer_name', upper($title['payer_name'] ?? ''))
            ->set('payer_address', upper($title['payer_address'] ?? ''))
            ->set('occurrence_code', (int) ($title['occurrence_code'] ?? 1))
            ->set('record_sequence', $seq++));
    }

    $file->add((new Record($layout->record('9')))
        ->set('record_type', 9)
        ->set('record_sequence', $seq));

    $content = (new Writer)->write($file);

    // Round-trip to prove the output is well-formed and to drive the preview.
    $parsed = (new Parser)->parse($content, $layout);

    $cents = 0;
    foreach ($parsed->details() as $detail) {
        $cents += (int) Decimal::toScaledInt($detail->get('amount'), 2);
    }

    return [
        'layout' => $layout->name,
        'lineLength' => $layout->lineLength,
        'content' => $content,
        'byteLength' => strlen($content),
        'lineCount' => $parsed->count(),
        'records' => array_map(serializeRecord(...), $parsed->records()),
        'summary' => [
            'records' => $parsed->count(),
            'details' => count($parsed->details()),
            'totalAmount' => Decimal::fromScaledInt((string) $cents, 2),
        ],
    ];
}

/** @return array{code:string,name:string,role:string,fields:list<array<string,mixed>>} */
function serializeRecord(Record $record): array
{
    $definition = $record->definition;

    $fields = [];
    foreach ($definition->fields as $name => $field) {
        if (str_starts_with($name, 'filler_')) {
            continue;
        }

        $fields[] = [
            'name' => $name,
            'label' => $field->description !== '' ? $field->description : $name,
            'value' => $record->tryGet($name, ''),
            'type' => $field->type->value,
            'span' => sprintf('%d–%d', $field->start, $field->end()),
        ];
    }

    return [
        'code' => $definition->code,
        'name' => $definition->name !== '' ? $definition->name : 'Record '.$definition->code,
        'role' => roleOf($definition),
        'fields' => $fields,
    ];
}

function roleOf(RecordDefinition $definition): string
{
    return match ($definition->code) {
        '0' => 'header',
        '9' => 'trailer',
        default => 'detail',
    };
}

function digits(mixed $value): string
{
    return preg_replace('/\D/', '', (string) $value) ?: '0';
}

function upper(mixed $value): string
{
    // CNAB alphanumeric: uppercase, ASCII, no accents.
    $text = (string) $value;
    $text = (string) preg_replace('/[^\x20-\x7E]/', '', toAscii($text));

    return strtoupper($text);
}

function toAscii(string $text): string
{
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);

    return $converted === false ? $text : $converted;
}

function sanitizeAmount(mixed $value): string
{
    return str_replace(',', '.', trim((string) $value)) ?: '0';
}

/** Accept "YYYY-MM-DD" (HTML date input) or an already-formatted DDMMYY. */
function toDdMmYy(mixed $value): string
{
    $value = trim((string) $value);

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return $m[3].$m[2].substr($m[1], 2, 2);
    }

    return digits($value);
}

/** @return array<string, mixed> */
function jsonBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function json(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function fail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
}
