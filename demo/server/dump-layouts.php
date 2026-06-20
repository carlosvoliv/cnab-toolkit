<?php

declare(strict_types=1);

/**
 * Dump the PUBLIC layouts to JSON so the static (GitHub Pages) build can run a
 * browser-side CNAB engine with the exact same field positions as the PHP
 * library — single source of truth, no hand-duplicated maps.
 *
 *   php demo/server/dump-layouts.php
 *
 * Private layouts in layouts.local/ are intentionally NOT dumped: they stay
 * server-side only and never reach the public static bundle.
 */

use Cnab\Layouts\Cnab240Cobranca;
use Cnab\Layouts\GenericRemittance550;
use Cnab\Schema\Layout;

require __DIR__.'/../../vendor/autoload.php';

/** @var array<string, array{label:string, layout:Layout, canGenerate:bool}> $public */
$public = [
    'generic-remittance-550' => [
        'label' => 'Genérico (exemplo) · 550',
        'layout' => GenericRemittance550::layout(),
        'canGenerate' => true,
    ],
    'cnab240-cobranca' => [
        'label' => 'CNAB240 Cobrança · BB (padrão Febraban)',
        'layout' => Cnab240Cobranca::layout(),
        'canGenerate' => true,
    ],
];

$out = [];

foreach ($public as $id => $meta) {
    $layout = $meta['layout'];

    $records = [];
    foreach ($layout->records as $code => $definition) {
        $fields = [];
        foreach ($definition->fields as $name => $field) {
            $fields[] = [
                'name' => $name,
                'start' => $field->start,
                'length' => $field->length,
                'type' => $field->type->value,
                'decimals' => $field->decimals,
                'description' => $field->description,
                'filler' => str_starts_with($name, 'filler_'),
            ];
        }

        $records[$code] = [
            'code' => $definition->code,
            'name' => $definition->name,
            'role' => match ($definition->code) {
                $layout->headerCode => 'header',
                $layout->trailerCode => 'trailer',
                default => 'detail',
            },
            'fields' => $fields,
        ];
    }

    $out[$id] = [
        'id' => $id,
        'label' => $meta['label'],
        'lineLength' => $layout->lineLength,
        'family' => 'CNAB'.$layout->lineLength,
        'canGenerate' => $meta['canGenerate'],
        'headerCode' => $layout->headerCode,
        'detailCode' => $layout->detailCode,
        'trailerCode' => $layout->trailerCode,
        'typeStart' => $layout->typeStart,
        'typeLength' => $layout->typeLength,
        'segmentStart' => $layout->segmentStart,
        'segmentLength' => $layout->segmentLength,
        'segmentParents' => $layout->segmentParents,
        'records' => $records,
    ];
}

$target = __DIR__.'/../src/layouts.generated.json';
file_put_contents($target, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

fwrite(STDERR, 'Wrote '.count($out)." layouts to $target\n");
