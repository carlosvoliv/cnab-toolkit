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
 * Layouts come from two places:
 *   - the public, didactic ones registered below;
 *   - any private map dropped in ./layouts.local/*.php (gitignored), e.g. an
 *     institution-specific CNAB550 used to read real files locally.
 */

use Cnab\CnabFile;
use Cnab\Exceptions\CnabException;
use Cnab\LayoutRegistry;
use Cnab\Layouts\Cnab240Cobranca;
use Cnab\Layouts\GenericRemittance550;
use Cnab\Parser;
use Cnab\Record;
use Cnab\Schema\Layout;
use Cnab\Schema\RecordDefinition;
use Cnab\Support\Decimal;
use Cnab\Writer;

require __DIR__.'/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

/** @var array<string, callable(Layout, array, array): CnabFile> $builders */
$builders = [];
$registry = new LayoutRegistry;

// Public layouts (ship with the repo).
$registry->register('generic-remittance-550', GenericRemittance550::layout(), 'Genérico (exemplo) · 550');
$builders['generic-remittance-550'] = buildGenericRemittance(...);

$registry->register('cnab240-cobranca', Cnab240Cobranca::layout(), 'CNAB240 Cobrança · BB (padrão Febraban)');
$builders['cnab240-cobranca'] = buildCnab240Cobranca(...);

// Private, local layouts (gitignored) — real maps to read actual files.
foreach (glob(__DIR__.'/layouts.local/*.php') ?: [] as $localFile) {
    $desc = require $localFile;
    if (! is_array($desc) || ! ($desc['layout'] ?? null) instanceof Layout) {
        continue;
    }
    $registry->register($desc['id'], $desc['layout'], $desc['label'] ?? $desc['id']);
    if (! empty($desc['builder']) && is_callable($desc['builder'])) {
        $builders[$desc['id']] = $desc['builder'];
    }
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    match (true) {
        $method === 'GET' && $path === '/api/layouts' => json(layoutCatalog($registry, $builders)),
        $method === 'POST' && $path === '/api/generate' => json(handleGenerate($registry, $builders, jsonBody())),
        $method === 'POST' && $path === '/api/parse' => json(handleParse($registry, jsonBody())),
        default => fail(404, 'Not found.'),
    };
} catch (CnabException $e) {
    fail(422, $e->getMessage());
} catch (Throwable $e) {
    fail(500, $e->getMessage());
}

/**
 * @param  array<string, callable>  $builders
 * @return array{layouts:list<array<string,mixed>>}
 */
function layoutCatalog(LayoutRegistry $registry, array $builders): array
{
    $layouts = array_map(
        static fn (array $meta): array => [...$meta, 'canGenerate' => isset($builders[$meta['id']])],
        $registry->describe(),
    );

    return ['layouts' => $layouts];
}

/**
 * @param  array<string, callable>  $builders
 * @param  array<string, mixed>  $input
 */
function handleGenerate(LayoutRegistry $registry, array $builders, array $input): array
{
    $id = (string) ($input['layout'] ?? '');

    if (! $registry->has($id)) {
        throw new CnabException('Layout desconhecido.');
    }
    if (! isset($builders[$id])) {
        throw new CnabException('Geração não disponível para este layout — use a leitura.');
    }

    $layout = $registry->get($id);
    $header = is_array($input['header'] ?? null) ? $input['header'] : [];
    $titles = is_array($input['titles'] ?? null) ? array_values($input['titles']) : [];

    if ($titles === []) {
        throw new CnabException('Adicione ao menos um título à remessa.');
    }

    $file = $builders[$id]($layout, $header, $titles);
    $content = (new Writer)->write($file);

    // Round-trip so the preview reflects exactly what was encoded.
    return payload($layout, (new Parser)->parse($content, $layout), $content);
}

/** @param array<string, mixed> $input */
function handleParse(LayoutRegistry $registry, array $input): array
{
    $id = (string) ($input['layout'] ?? '');

    if (! $registry->has($id)) {
        throw new CnabException('Layout desconhecido.');
    }

    // Real CNAB files are latin-1 and byte-positioned; uploads arrive base64 so
    // raw bytes survive (accented chars stay single-byte and line width holds).
    if (isset($input['contentBase64'])) {
        $content = base64_decode((string) $input['contentBase64'], true);
        if ($content === false) {
            throw new CnabException('Conteúdo base64 inválido.');
        }
    } else {
        $content = (string) ($input['content'] ?? '');
    }

    $content = rtrim($content);

    if (trim($content) === '') {
        throw new CnabException('Cole o conteúdo de um arquivo CNAB ou envie um .REM.');
    }

    $layout = $registry->get($id);

    return payload($layout, (new Parser)->parse($content, $layout), null);
}

/** The generic builder used by the public example layout. */
function buildGenericRemittance(Layout $layout, array $header, array $titles): CnabFile
{
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

    return $file;
}

/** Build a CNAB240 cobrança remittance: file/lot headers, P+Q per title, trailers. */
function buildCnab240Cobranca(Layout $layout, array $header, array $titles): CnabFile
{
    $file = new CnabFile($layout);
    $bank = str_pad(digits($header['bank_code'] ?? '1'), 3, '0', STR_PAD_LEFT);
    $today = date('dmY');

    $file->add((new Record($layout->record('0')))
        ->set('bank_code', $bank)
        ->set('lot', 0)
        ->set('record_type', 0)
        ->set('company_doc_type', 2)
        ->set('company_document', digits($header['company_code'] ?? '0'))
        ->set('company_name', upper($header['company_name'] ?? ''))
        ->set('bank_name', upper($header['bank_name'] ?? ''))
        ->set('file_code', 1)
        ->set('file_date', $today)
        ->set('file_sequence', digits($header['file_sequence'] ?? '1'))
        ->set('layout_version', 103));

    $file->add((new Record($layout->record('1')))
        ->set('bank_code', $bank)
        ->set('lot', 1)
        ->set('record_type', 1)
        ->set('operation_type', 'R')
        ->set('service_type', 1)
        ->set('lot_layout_version', 60)
        ->set('company_doc_type', 2)
        ->set('company_document', digits($header['company_code'] ?? '0'))
        ->set('company_name', upper($header['company_name'] ?? ''))
        ->set('recording_date', $today));

    $rn = 0;
    foreach ($titles as $i => $title) {
        if (! is_array($title)) {
            continue;
        }

        $file->add((new Record($layout->record('3P')))
            ->set('bank_code', $bank)
            ->set('lot', 1)
            ->set('record_type', 3)
            ->set('record_number', ++$rn)
            ->set('segment', 'P')
            ->set('movement_code', 1)
            ->set('document_number', upper($title['document_number'] ?? sprintf('DOC%04d', $i + 1)))
            ->set('due_date', toDdMmAaaa($title['due_date'] ?? ''))
            ->set('nominal_value', sanitizeAmount($title['amount'] ?? '0'))
            ->set('title_species', 2)
            ->set('emission_date', $today)
            ->set('currency_code', 9));

        $file->add((new Record($layout->record('3Q')))
            ->set('bank_code', $bank)
            ->set('lot', 1)
            ->set('record_type', 3)
            ->set('record_number', ++$rn)
            ->set('segment', 'Q')
            ->set('movement_code', 1)
            ->set('payer_doc_type', (int) ($title['payer_doc_type'] ?? 1))
            ->set('payer_document', digits($title['payer_document'] ?? '0'))
            ->set('payer_name', upper($title['payer_name'] ?? ''))
            ->set('payer_address', upper($title['payer_address'] ?? '')));
    }

    $lotRecords = $rn + 2; // lot header + details + lot trailer

    $file->add((new Record($layout->record('5')))
        ->set('bank_code', $bank)
        ->set('lot', 1)
        ->set('record_type', 5)
        ->set('lot_record_count', $lotRecords));

    $file->add((new Record($layout->record('9')))
        ->set('bank_code', $bank)
        ->set('lot', 9999)
        ->set('record_type', 9)
        ->set('lot_count', 1)
        ->set('record_count', $lotRecords + 2)); // + file header + file trailer

    return $file;
}

/** Build the decoded payload (records + summary), optionally with raw content. */
function payload(Layout $layout, CnabFile $file, ?string $content): array
{
    $cents = 0;
    foreach ($file->details() as $detail) {
        foreach (['amount', 'title_amount', 'nominal_value'] as $key) {
            if ($detail->has($key) && $detail->get($key) !== '') {
                $cents += (int) Decimal::toScaledInt($detail->get($key), 2);
                break;
            }
        }
    }

    $out = [
        'layout' => $layout->name,
        'lineLength' => $layout->lineLength,
        'records' => array_map(serializeRecord(...), $file->records()),
        'summary' => [
            'records' => $file->count(),
            'details' => count($file->details()),
            'totalAmount' => Decimal::fromScaledInt((string) $cents, 2),
        ],
    ];

    if ($content !== null) {
        $out['content'] = $content;
        $out['byteLength'] = strlen($content);
    }

    return $out;
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
            'value' => toUtf8($record->tryGet($name, '')),
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
    $text = (string) preg_replace('/[^\x20-\x7E]/', '', toAscii((string) $value));

    return strtoupper($text);
}

function toAscii(string $text): string
{
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);

    return $converted === false ? $text : $converted;
}

/** Ensure a field value is valid UTF-8 for JSON (latin-1 files decode to it). */
function toUtf8(string $text): string
{
    if ($text === '' || mb_check_encoding($text, 'UTF-8')) {
        return $text;
    }

    return mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
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

/** Accept "YYYY-MM-DD" (HTML date input) and return DDMMAAAA (CNAB240). */
function toDdMmAaaa(mixed $value): string
{
    $value = trim((string) $value);

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return $m[3].$m[2].$m[1];
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
