# cnab-toolkit

A small, dependency-free PHP library to **read, write and validate Brazilian
CNAB fixed-width bank files** (remittance/return — *remessa/retorno*).

Instead of hardcoding one bank's table of fields, layouts are described as
**data**: you declare records and fields once, and the same engine parses,
generates and validates any CNAB variant — 240, 400, 550 or a custom width.

[![CI](https://github.com/carlosvoliv/cnab-toolkit/actions/workflows/ci.yml/badge.svg)](https://github.com/carlosvoliv/cnab-toolkit/actions/workflows/ci.yml)

## Why

CNAB files are flat, fixed-width text where every field lives at an absolute
column range and follows strict padding rules:

| Type | Picture | Alignment | Padding |
|------|---------|-----------|---------|
| Numeric | `9(n)` | right | left zeros |
| Alphanumeric | `X(n)` | left | right spaces |

Money is stored as an integer with **implied decimals** — `0000000247056` with
two decimals means `2470.56`. Get any of this subtly wrong and the bank rejects
the whole file. This toolkit encodes those rules once, validates that a layout
actually tiles the line with no gaps or overlaps, and round-trips values
losslessly (digit strings, so a `9(20)` field never overflows a 64-bit int).

## Install

```bash
composer require carlosvoliv/cnab-toolkit
```

Requires PHP 8.2+. No runtime dependencies.

## Describe a layout

```php
use Cnab\Schema\{Field, Layout, RecordDefinition};

$layout = new Layout(
    name: 'my-remittance',
    lineLength: 550,
    records: [
        new RecordDefinition('0', 550, name: 'Header', fields: [
            Field::numeric('record_type', 1, 1),
            Field::alpha('literal', 2, 8, 'REMESSA'),
            // ...
            Field::filler(93, 452),
            Field::numeric('record_sequence', 545, 6),
        ]),
        // detail '1', trailer '9' ...
    ],
);
```

If two fields overlap, leave a gap, or the record does not fill the line, the
constructor throws a `LayoutException` — you find out at boot, not in
production.

A ready-made generic 550 layout ships in the box:

```php
use Cnab\Layouts\GenericRemittance550;

$layout = GenericRemittance550::layout();
```

## Write a file

```php
use Cnab\{CnabFile, Record, Writer};

$file = new CnabFile($layout);

$file->add((new Record($layout->record('1')))
    ->set('record_type', 1)
    ->set('amount', '2470.56')        // -> 0000000247056
    ->set('payer_name', 'ACME LTDA')  // -> "ACME LTDA" + right padding
    ->set('record_sequence', 2));

$content = (new Writer())->write($file); // CRLF-separated, 550 cols each
```

## Parse a file

```php
use Cnab\Parser;

$file = (new Parser())->parse($content, $layout);

$file->header()->get('company_name');     // "ACME SECURITIES"
$file->details()[0]->get('amount');        // "2470.56"
$file->trailer()->get('record_sequence');  // "3"
```

The parser accepts both CRLF/LF-delimited files and a single uninterrupted
stream whose length is a multiple of the line length.

## Design

```
Schema/      Field, RecordDefinition, Layout  — the declarative layout model
Formatting/  FieldCodec                       — padding/alignment rules
Support/     Decimal                          — string-based fixed-point scaling
Parser / Writer / CnabFile / Record           — the I/O surface
```

## Scope & disclaimer

This is an independent, clean-room implementation built from the **public**
CNAB conventions (Febraban fixed-width record structure, picture notation,
padding and implied-decimal rules). It ships **no** institution-specific field
sets, client names or proprietary business rules — adapt the layouts to your
own bank's manual.

## Tests

```bash
composer install
composer test    # phpunit
composer lint    # pint --test
```

## License

MIT © Carlos E V Oliveira
