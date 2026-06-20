// Browser-side CNAB engine — a faithful JS port of the PHP library's codec,
// parser and writer, driven by layout schemas dumped from PHP
// (layouts.generated.json) so field positions stay single-sourced.
//
// Used only as a fallback when no PHP backend is available (e.g. the static
// GitHub Pages build). Locally, the demo talks to the real PHP API.

import layouts from '../layouts.generated.json'

export { layouts }

// ── Fixed-point scaling (string-based, mirrors Support\Decimal) ─────────────
function toScaledInt(value, scale) {
  let raw = String(value).replace(',', '.').trim()
  const negative = raw.startsWith('-')
  raw = raw.replace(/^[+-]/, '')
  if (raw === '' || !/^\d*(\.\d*)?$/.test(raw)) throw new Error(`Valor decimal inválido: "${value}"`)
  let [intPart, fracPart = ''] = raw.split('.')
  fracPart = fracPart.padEnd(scale, '0').slice(0, scale)
  let digits = (intPart + fracPart).replace(/^0+/, '') || '0'
  return negative && digits !== '0' ? '-' + digits : digits
}

function fromScaledInt(scaledInt, scale) {
  const negative = String(scaledInt).startsWith('-')
  let digits = String(scaledInt).replace(/^[+-]/, '').replace(/^0+/, '') || '0'
  if (scale === 0) return negative && digits !== '0' ? '-' + digits : digits
  digits = digits.padStart(scale + 1, '0')
  const intPart = digits.slice(0, -scale)
  const fracPart = digits.slice(-scale)
  const result = `${intPart}.${fracPart}`
  return negative && Number(digits) !== 0 ? '-' + result : result
}

// ── Field codec (mirrors Formatting\FieldCodec) ─────────────────────────────
function encodeField(field, value) {
  value = value ?? ''
  if (field.type === 'numeric') {
    const scaled = value === '' ? '0' : toScaledInt(value, field.decimals)
    if (scaled.startsWith('-')) throw new Error(`Campo "${field.name}" não aceita negativo.`)
    if (scaled.length > field.length)
      throw new Error(`Valor "${value}" não cabe no campo numérico "${field.name}" (${field.length}).`)
    return scaled.padStart(field.length, '0')
  }
  const text = String(value)
  if (text.length > field.length)
    throw new Error(`Valor "${text}" não cabe no campo "${field.name}" (${field.length}).`)
  return text.padEnd(field.length, ' ')
}

function decodeField(field, raw) {
  if (field.type === 'numeric') {
    return field.decimals > 0 ? fromScaledInt(raw === '' ? '0' : raw, field.decimals) : raw
  }
  return raw.replace(/ +$/, '')
}

// ── Record-type resolution (supports CNAB240 segments) ──────────────────────
function codeOf(layout, line) {
  const primary = line.substr(layout.typeStart - 1, layout.typeLength)
  if (layout.segmentLength > 0 && layout.segmentParents.includes(primary)) {
    return primary + line.substr(layout.segmentStart - 1, layout.segmentLength)
  }
  return primary
}

function splitLines(content, lineLength) {
  content = content.replace(/[\r\n]+$/, '')
  if (content === '') return []
  if (content.includes('\n')) return content.split('\n').map((l) => l.replace(/\r$/, ''))
  const out = []
  for (let i = 0; i < content.length; i += lineLength) out.push(content.slice(i, i + lineLength))
  return out
}

// ── Parser ──────────────────────────────────────────────────────────────────
export function parseContent(layout, content) {
  content = content.replace(/[\r\n]+$/, '')
  if (content.trim() === '') throw new Error('Cole o conteúdo de um arquivo CNAB ou envie um .REM.')

  const lines = splitLines(content, layout.lineLength)
  return lines.map((line, idx) => {
    if (line.length !== layout.lineLength)
      throw new Error(`Linha ${idx + 1} tem ${line.length} colunas, esperado ${layout.lineLength}.`)
    const code = codeOf(layout, line)
    const def = layout.records[code]
    if (!def) throw new Error(`Linha ${idx + 1}: tipo de registro desconhecido "${code}".`)
    const values = {}
    for (const field of def.fields) {
      values[field.name] = decodeField(field, line.substr(field.start - 1, field.length))
    }
    return { def, values }
  })
}

// ── Writer ──────────────────────────────────────────────────────────────────
export function writeFile(layout, records) {
  const lines = records.map(({ code, values }) => {
    const def = layout.records[code]
    return def.fields.map((f) => encodeField(f, values[f.name])).join('')
  })
  return lines.join('\r\n') + (lines.length ? '\r\n' : '')
}

// ── Payload shaping (mirrors the PHP /api response) ─────────────────────────
function serializeRecord(rec) {
  const fields = rec.def.fields
    .filter((f) => !f.filler)
    .map((f) => ({
      name: f.name,
      label: f.description || f.name,
      value: rec.values[f.name] ?? '',
      type: f.type,
      span: `${f.start}–${f.start + f.length - 1}`,
    }))
  return { code: rec.def.code, name: rec.def.name, role: rec.def.role, fields }
}

const VALUE_KEYS = ['amount', 'title_amount', 'nominal_value']

export function payload(layout, records, content) {
  let cents = 0
  for (const rec of records) {
    if (rec.def.code !== layout.detailCode) continue
    for (const key of VALUE_KEYS) {
      if (rec.values[key] != null && rec.values[key] !== '') {
        cents += parseInt(toScaledInt(rec.values[key], 2), 10) || 0
        break
      }
    }
  }
  const details = records.filter((r) => r.def.code === layout.detailCode).length
  const out = {
    layout: layout.id,
    lineLength: layout.lineLength,
    records: records.map(serializeRecord),
    summary: { records: records.length, details, totalAmount: fromScaledInt(String(cents), 2) },
  }
  if (content != null) {
    out.content = content
    out.byteLength = content.length
  }
  return out
}
