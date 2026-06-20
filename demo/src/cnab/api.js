// Unified data access: talk to the real PHP API when it's there (local dev /
// PHP deploy), otherwise fall back to the in-browser engine (static build).

import { layouts, parseContent, writeFile, payload } from './engine'
import { BUILDERS } from './builders'

let apiAvailable = null

export async function hasApi() {
  if (apiAvailable !== null) return apiAvailable
  try {
    const res = await fetch('/api/layouts', { method: 'GET' })
    apiAvailable = res.ok
  } catch {
    apiAvailable = false
  }
  return apiAvailable
}

async function postJson(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok) throw new Error(data.error || `Falha na requisição (${res.status})`)
  return data
}

export async function getLayouts() {
  if (await hasApi()) {
    return (await (await fetch('/api/layouts')).json()).layouts
  }
  return Object.values(layouts).map((l) => ({
    id: l.id,
    label: l.label,
    lineLength: l.lineLength,
    family: l.family,
    canGenerate: l.canGenerate,
  }))
}

export async function generate(layoutId, header, titles) {
  if (await hasApi()) {
    return postJson('/api/generate', { layout: layoutId, header, titles })
  }
  const layout = layouts[layoutId]
  const builder = BUILDERS[layoutId]
  if (!layout) throw new Error('Layout desconhecido.')
  if (!builder) throw new Error('Geração não disponível para este layout — use a leitura.')
  if (!titles.length) throw new Error('Adicione ao menos um título à remessa.')
  const content = writeFile(layout, builder(header, titles))
  return payload(layout, parseContent(layout, content), content)
}

export async function parse(layoutId, { content, contentBase64 }) {
  if (await hasApi()) {
    const body = { layout: layoutId }
    if (contentBase64) body.contentBase64 = contentBase64
    else body.content = content
    return postJson('/api/parse', body)
  }
  const layout = layouts[layoutId]
  if (!layout) throw new Error('Layout desconhecido.')
  const raw = contentBase64 ? atob(contentBase64) : content || ''
  return payload(layout, parseContent(layout, raw), null)
}
