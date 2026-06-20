<?php

declare(strict_types=1);

namespace Cnab\Layouts;

use Cnab\Schema\Field;
use Cnab\Schema\Layout;
use Cnab\Schema\RecordDefinition;

/**
 * CNAB240 collection (cobrança) remittance — the FEBRABAN segment-based layout.
 *
 * Unlike the flat header/detail/trailer files, CNAB240 nests records:
 *
 *   0  file header
 *   1  lot (lote) header
 *   3P detail — segment P (título: amounts, dates, instructions)
 *   3Q detail — segment Q (payer/guarantor data)
 *   5  lot trailer
 *   9  file trailer
 *
 * The record type lives at column 8; detail records (type 3) are further
 * identified by a segment letter at column 14, so codes become "3P"/"3Q".
 *
 * Field positions follow the FEBRABAN CNAB240 standard; the header's convênio
 * area is modelled on the Banco do Brasil variant. Money fields are 9(13)V9(2).
 */
final class Cnab240Cobranca
{
    public const LINE_LENGTH = 240;

    public static function layout(): Layout
    {
        return new Layout(
            name: 'cnab240-cobranca',
            lineLength: self::LINE_LENGTH,
            records: [
                self::fileHeader(),
                self::lotHeader(),
                self::segmentP(),
                self::segmentQ(),
                self::lotTrailer(),
                self::fileTrailer(),
            ],
            headerCode: '0',
            detailCode: '3P',
            trailerCode: '9',
            typeStart: 8,
            typeLength: 1,
            segmentStart: 14,
            segmentLength: 1,
            segmentParents: ['3'],
        );
    }

    private static function fileHeader(): RecordDefinition
    {
        return new RecordDefinition('0', self::LINE_LENGTH, name: 'Header de arquivo', fields: [
            Field::numeric('bank_code', 1, 3, description: 'Código do banco'),
            Field::numeric('lot', 4, 4, description: 'Lote de serviço'),
            Field::numeric('record_type', 8, 1, description: 'Tipo de registro (0)'),
            Field::filler(9, 9),
            Field::numeric('company_doc_type', 18, 1, description: 'Tipo de inscrição da empresa'),
            Field::numeric('company_document', 19, 14, description: 'Nº de inscrição da empresa'),
            Field::numeric('agreement_number', 33, 9, description: 'Número do convênio'),
            Field::numeric('assignor_code', 42, 4, description: 'Cobrança cedente'),
            Field::numeric('wallet_number', 46, 2, description: 'Número da carteira'),
            Field::numeric('wallet_variation', 48, 3, description: 'Variação da carteira'),
            Field::filler(51, 2),
            Field::numeric('agency', 53, 5, description: 'Agência mantenedora'),
            Field::alpha('agency_dv', 58, 1, 'Dígito verificador da agência'),
            Field::numeric('account', 59, 12, description: 'Número da conta'),
            Field::alpha('account_dv', 71, 1, 'Dígito verificador da conta'),
            Field::alpha('agency_account_dv', 72, 1, 'DV agência/conta'),
            Field::alpha('company_name', 73, 30, 'Nome da empresa'),
            Field::alpha('bank_name', 103, 30, 'Nome do banco'),
            Field::filler(133, 10),
            Field::numeric('file_code', 143, 1, description: 'Código remessa/retorno'),
            Field::numeric('file_date', 144, 8, description: 'Data de geração DDMMAAAA'),
            Field::numeric('file_time', 152, 6, description: 'Hora de geração HHMMSS'),
            Field::numeric('file_sequence', 158, 6, description: 'Nº sequencial do arquivo'),
            Field::numeric('layout_version', 164, 3, description: 'Versão do layout do arquivo'),
            Field::numeric('recording_density', 167, 5, description: 'Densidade de gravação'),
            Field::filler(172, 20),
            Field::filler(192, 20),
            Field::filler(212, 29),
        ]);
    }

    private static function lotHeader(): RecordDefinition
    {
        return new RecordDefinition('1', self::LINE_LENGTH, name: 'Header de lote', fields: [
            Field::numeric('bank_code', 1, 3, description: 'Código do banco'),
            Field::numeric('lot', 4, 4, description: 'Lote de serviço'),
            Field::numeric('record_type', 8, 1, description: 'Tipo de registro (1)'),
            Field::alpha('operation_type', 9, 1, 'Tipo de operação (R)'),
            Field::numeric('service_type', 10, 2, description: 'Tipo de serviço (01)'),
            Field::filler(12, 2),
            Field::numeric('lot_layout_version', 14, 3, description: 'Versão do layout do lote'),
            Field::filler(17, 1),
            Field::numeric('company_doc_type', 18, 1, description: 'Tipo de inscrição da empresa'),
            Field::numeric('company_document', 19, 15, description: 'Nº de inscrição da empresa'),
            Field::numeric('agreement_number', 34, 9, description: 'Número do convênio'),
            Field::numeric('assignor_code', 43, 4, description: 'Cobrança cedente'),
            Field::numeric('wallet_number', 47, 2, description: 'Número da carteira'),
            Field::numeric('wallet_variation', 49, 3, description: 'Variação da carteira'),
            Field::filler(52, 2),
            Field::numeric('agency', 54, 5, description: 'Agência mantenedora'),
            Field::alpha('agency_dv', 59, 1, 'Dígito verificador da agência'),
            Field::numeric('account', 60, 12, description: 'Número da conta'),
            Field::alpha('account_dv', 72, 1, 'Dígito verificador da conta'),
            Field::alpha('agency_account_dv', 73, 1, 'DV agência/conta'),
            Field::alpha('company_name', 74, 30, 'Nome da empresa'),
            Field::alpha('message_1', 104, 40, 'Mensagem 1'),
            Field::alpha('message_2', 144, 40, 'Mensagem 2'),
            Field::numeric('remittance_number', 184, 8, description: 'Nº remessa/retorno'),
            Field::numeric('recording_date', 192, 8, description: 'Data de gravação DDMMAAAA'),
            Field::numeric('credit_date', 200, 8, description: 'Data do crédito DDMMAAAA'),
            Field::filler(208, 33),
        ]);
    }

    private static function segmentP(): RecordDefinition
    {
        return new RecordDefinition('3P', self::LINE_LENGTH, name: 'Detalhe · Segmento P', fields: [
            Field::numeric('bank_code', 1, 3, description: 'Código do banco'),
            Field::numeric('lot', 4, 4, description: 'Lote de serviço'),
            Field::numeric('record_type', 8, 1, description: 'Tipo de registro (3)'),
            Field::numeric('record_number', 9, 5, description: 'Nº do registro no lote'),
            Field::alpha('segment', 14, 1, 'Código do segmento (P)'),
            Field::filler(15, 1),
            Field::numeric('movement_code', 16, 2, description: 'Código de movimento remessa'),
            Field::numeric('agency', 18, 5, description: 'Agência'),
            Field::alpha('agency_dv', 23, 1, 'DV da agência'),
            Field::numeric('account', 24, 12, description: 'Número da conta'),
            Field::alpha('account_dv', 36, 1, 'DV da conta'),
            Field::alpha('agency_account_dv', 37, 1, 'DV agência/conta'),
            Field::alpha('our_number', 38, 20, 'Nosso número'),
            Field::numeric('wallet_code', 58, 1, description: 'Código da carteira'),
            Field::numeric('registration_form', 59, 1, description: 'Forma de cadastramento'),
            Field::alpha('document_type', 60, 1, 'Tipo de documento'),
            Field::numeric('bloqueto_emission', 61, 1, description: 'Emissão do bloqueto'),
            Field::alpha('bloqueto_distribution', 62, 1, 'Distribuição do bloqueto'),
            Field::alpha('document_number', 63, 15, 'Nº do documento'),
            Field::numeric('due_date', 78, 8, description: 'Data de vencimento DDMMAAAA'),
            Field::numeric('nominal_value', 86, 15, decimals: 2, description: 'Valor nominal do título'),
            Field::numeric('collection_agency', 101, 5, description: 'Agência cobradora'),
            Field::alpha('collection_agency_dv', 106, 1, 'DV da agência cobradora'),
            Field::numeric('title_species', 107, 2, description: 'Espécie do título'),
            Field::alpha('acceptance', 109, 1, 'Aceite'),
            Field::numeric('emission_date', 110, 8, description: 'Data de emissão DDMMAAAA'),
            Field::numeric('interest_code', 118, 1, description: 'Código de juros de mora'),
            Field::numeric('interest_date', 119, 8, description: 'Data de juros de mora'),
            Field::numeric('interest_per_day', 127, 15, decimals: 2, description: 'Juros de mora por dia'),
            Field::numeric('discount_code', 142, 1, description: 'Código de desconto'),
            Field::numeric('discount_date', 143, 8, description: 'Data de desconto'),
            Field::numeric('discount_value', 151, 15, decimals: 2, description: 'Valor do desconto'),
            Field::numeric('iof_value', 166, 15, decimals: 2, description: 'Valor do IOF'),
            Field::numeric('rebate_value', 181, 15, decimals: 2, description: 'Valor do abatimento'),
            Field::alpha('company_title_id', 196, 25, 'Identificação do título na empresa'),
            Field::numeric('protest_code', 221, 1, description: 'Código de protesto'),
            Field::numeric('protest_days', 222, 2, description: 'Prazo para protesto'),
            Field::numeric('writeoff_code', 224, 1, description: 'Código de baixa/devolução'),
            Field::alpha('writeoff_days', 225, 3, 'Prazo para baixa/devolução'),
            Field::numeric('currency_code', 228, 2, description: 'Código da moeda'),
            Field::numeric('credit_contract_number', 230, 10, description: 'Nº do contrato'),
            Field::filler(240, 1),
        ]);
    }

    private static function segmentQ(): RecordDefinition
    {
        return new RecordDefinition('3Q', self::LINE_LENGTH, name: 'Detalhe · Segmento Q', fields: [
            Field::numeric('bank_code', 1, 3, description: 'Código do banco'),
            Field::numeric('lot', 4, 4, description: 'Lote de serviço'),
            Field::numeric('record_type', 8, 1, description: 'Tipo de registro (3)'),
            Field::numeric('record_number', 9, 5, description: 'Nº do registro no lote'),
            Field::alpha('segment', 14, 1, 'Código do segmento (Q)'),
            Field::filler(15, 1),
            Field::numeric('movement_code', 16, 2, description: 'Código de movimento remessa'),
            Field::numeric('payer_doc_type', 18, 1, description: 'Tipo de inscrição do sacado'),
            Field::numeric('payer_document', 19, 15, description: 'Nº de inscrição do sacado'),
            Field::alpha('payer_name', 34, 40, 'Nome do sacado'),
            Field::alpha('payer_address', 74, 40, 'Endereço'),
            Field::alpha('payer_district', 114, 15, 'Bairro'),
            Field::numeric('payer_zip', 129, 5, description: 'CEP'),
            Field::numeric('payer_zip_suffix', 134, 3, description: 'Sufixo do CEP'),
            Field::alpha('payer_city', 137, 15, 'Cidade'),
            Field::alpha('payer_state', 152, 2, 'UF'),
            Field::numeric('guarantor_doc_type', 154, 1, description: 'Tipo de inscrição do sacador'),
            Field::numeric('guarantor_document', 155, 15, description: 'Nº de inscrição do sacador'),
            Field::alpha('guarantor_name', 170, 40, 'Nome do sacador/avalista'),
            Field::numeric('correspondent_bank', 210, 3, description: 'Banco correspondente'),
            Field::alpha('correspondent_our_number', 213, 20, 'Nosso número no banco correspondente'),
            Field::filler(233, 8),
        ]);
    }

    private static function lotTrailer(): RecordDefinition
    {
        return new RecordDefinition('5', self::LINE_LENGTH, name: 'Trailer de lote', fields: [
            Field::numeric('bank_code', 1, 3, description: 'Código do banco'),
            Field::numeric('lot', 4, 4, description: 'Lote de serviço'),
            Field::numeric('record_type', 8, 1, description: 'Tipo de registro (5)'),
            Field::filler(9, 9),
            Field::numeric('lot_record_count', 18, 6, description: 'Quantidade de registros do lote'),
            Field::filler(24, 217),
        ]);
    }

    private static function fileTrailer(): RecordDefinition
    {
        return new RecordDefinition('9', self::LINE_LENGTH, name: 'Trailer de arquivo', fields: [
            Field::numeric('bank_code', 1, 3, description: 'Código do banco'),
            Field::numeric('lot', 4, 4, description: 'Lote de serviço'),
            Field::numeric('record_type', 8, 1, description: 'Tipo de registro (9)'),
            Field::filler(9, 9),
            Field::numeric('lot_count', 18, 6, description: 'Quantidade de lotes do arquivo'),
            Field::numeric('record_count', 24, 6, description: 'Quantidade de registros do arquivo'),
            Field::numeric('reconciliation_account_count', 30, 6, description: 'Qtd contas p/ conciliação'),
            Field::filler(36, 205),
        ]);
    }
}
