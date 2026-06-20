// Browser-side remittance builders — JS port of the demo's PHP builders.
// Map the semantic form (header + titles) onto each layout's fields.

function digits(v) {
  return String(v ?? '').replace(/\D/g, '') || '0'
}

function upper(v) {
  return String(v ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '') // strip accents
    .replace(/[^\x20-\x7E]/g, '') // ASCII only
    .toUpperCase()
}

function sanitizeAmount(v) {
  return String(v ?? '').replace(',', '.').trim() || '0'
}

function toDdMmYy(v) {
  const m = String(v ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/)
  return m ? m[3] + m[2] + m[1].slice(2) : digits(v)
}

function toDdMmAaaa(v) {
  const m = String(v ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/)
  return m ? m[3] + m[2] + m[1] : digits(v)
}

function today(fmt) {
  const d = new Date()
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = String(d.getFullYear())
  return fmt === 'dmY' ? dd + mm + yyyy : dd + mm + yyyy.slice(2)
}

// → list of { code, values } records
export function buildGenericRemittance(header, titles) {
  const recs = []
  let seq = 1
  recs.push({
    code: '0',
    values: {
      record_type: 0,
      remittance_literal: 'REMESSA',
      service_code: header.service_code ?? 1,
      company_code: digits(header.company_code),
      company_name: upper(header.company_name),
      bank_code: digits(header.bank_code),
      bank_name: upper(header.bank_name),
      file_date: today('dmy'),
      file_sequence: digits(header.file_sequence),
      record_sequence: seq++,
    },
  })
  titles.forEach((t, i) => {
    recs.push({
      code: '1',
      values: {
        record_type: 1,
        control_number: upper(t.control_number || `CTRL-${String(i + 1).padStart(4, '0')}`),
        document_number: upper(t.document_number),
        due_date: toDdMmYy(t.due_date),
        amount: sanitizeAmount(t.amount),
        payer_doc_type: t.payer_doc_type ?? 1,
        payer_document: digits(t.payer_document),
        payer_name: upper(t.payer_name),
        payer_address: upper(t.payer_address),
        occurrence_code: t.occurrence_code ?? 1,
        record_sequence: seq++,
      },
    })
  })
  recs.push({ code: '9', values: { record_type: 9, record_sequence: seq } })
  return recs
}

export function buildCnab240Cobranca(header, titles) {
  const recs = []
  const bank = digits(header.bank_code).padStart(3, '0').slice(-3)
  const td = today('dmY')

  recs.push({
    code: '0',
    values: {
      bank_code: bank, lot: 0, record_type: 0,
      company_doc_type: 2, company_document: digits(header.company_code),
      company_name: upper(header.company_name), bank_name: upper(header.bank_name),
      file_code: 1, file_date: td, file_sequence: digits(header.file_sequence), layout_version: 103,
    },
  })
  recs.push({
    code: '1',
    values: {
      bank_code: bank, lot: 1, record_type: 1, operation_type: 'R', service_type: 1,
      lot_layout_version: 60, company_doc_type: 2, company_document: digits(header.company_code),
      company_name: upper(header.company_name), recording_date: td,
    },
  })

  let rn = 0
  titles.forEach((t, i) => {
    recs.push({
      code: '3P',
      values: {
        bank_code: bank, lot: 1, record_type: 3, record_number: ++rn, segment: 'P', movement_code: 1,
        document_number: upper(t.document_number || `DOC${String(i + 1).padStart(4, '0')}`),
        due_date: toDdMmAaaa(t.due_date), nominal_value: sanitizeAmount(t.amount),
        title_species: 2, emission_date: td, currency_code: 9,
      },
    })
    recs.push({
      code: '3Q',
      values: {
        bank_code: bank, lot: 1, record_type: 3, record_number: ++rn, segment: 'Q', movement_code: 1,
        payer_doc_type: t.payer_doc_type ?? 1, payer_document: digits(t.payer_document),
        payer_name: upper(t.payer_name), payer_address: upper(t.payer_address),
      },
    })
  })

  const lotRecords = rn + 2
  recs.push({ code: '5', values: { bank_code: bank, lot: 1, record_type: 5, lot_record_count: lotRecords } })
  recs.push({
    code: '9',
    values: { bank_code: bank, lot: 9999, record_type: 9, lot_count: 1, record_count: lotRecords + 2 },
  })
  return recs
}

export const BUILDERS = {
  'generic-remittance-550': buildGenericRemittance,
  'cnab240-cobranca': buildCnab240Cobranca,
}
