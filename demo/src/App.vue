<script setup>
import { ref, computed } from 'vue'
import {
  FacetButton,
  FacetIconButton,
  FacetInput,
  FacetTable,
  FacetChip,
  FacetAlert,
  FacetStepper,
  FacetTabs,
  FacetSelect,
  FacetIcon,
} from 'facet-ui'
import themeManifest from 'facet-ui/themes.json'

// ── Theme picker — list comes straight from facet-ui's exported manifest ───
const THEMES = [
  { value: 'facet-dark', label: 'Facet Dark' },
  { value: '', label: 'Facet Light' },
  ...themeManifest.map((t) => ({ value: t.name, label: t.label })),
]
const theme = ref('facet-dark')
function applyTheme(value) {
  theme.value = value
  if (value) document.documentElement.dataset.theme = value
  else delete document.documentElement.dataset.theme
}

const DOC_TYPES = [
  { value: '2', label: 'CNPJ' },
  { value: '1', label: 'CPF' },
]

// ── Form state ──────────────────────────────────────────────────────────────
function makeTitle(seed = {}) {
  return {
    payer_name: '',
    payer_doc_type: '2',
    payer_document: '',
    document_number: '',
    due_date: '',
    amount: '',
    ...seed,
  }
}

const header = ref({
  company_name: 'ACME SECURITIES LTDA',
  company_code: '1234567',
  bank_code: '341',
  bank_name: 'BANK',
  service_code: '1',
  file_sequence: '42',
})

const titles = ref([
  makeTitle({
    payer_name: 'PAYER ONE LTDA',
    payer_doc_type: '2',
    payer_document: '12.345.678/0001-99',
    document_number: 'DOC0001',
    due_date: '2026-06-30',
    amount: '2470.56',
  }),
  makeTitle({
    payer_name: 'Maria de Souza',
    payer_doc_type: '1',
    payer_document: '529.982.247-25',
    document_number: 'DOC0002',
    due_date: '2026-07-15',
    amount: '899.90',
  }),
])

const loading = ref(false)
const error = ref('')
const result = ref(null)
const view = ref('file') // file | decoded

const canGenerate = computed(() => titles.value.length > 0)

const steps = computed(() => {
  const done = !!result.value
  return [
    { label: 'Dados', state: 'done' },
    { label: 'Validado', state: done ? 'done' : 'idle' },
    { label: 'Gerado', state: done ? 'done' : 'idle' },
  ]
})

const totalBRL = computed(() => {
  if (!result.value) return ''
  const value = Number.parseFloat(result.value.summary.totalAmount || '0')
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
})

function roleVariant(role) {
  return role === 'header' ? 'blue' : role === 'trailer' ? 'grey' : 'ok'
}
function roleLabel(role) {
  return role === 'header' ? 'Header' : role === 'trailer' ? 'Trailer' : 'Detalhe'
}

function addTitle() {
  titles.value.push(makeTitle())
}
function removeTitle(i) {
  titles.value.splice(i, 1)
}

const fieldColumns = [
  { key: 'label', label: 'Campo' },
  { key: 'span', label: 'Posição', align: 'center' },
  { key: 'value', label: 'Valor' },
]

async function generate() {
  if (!canGenerate.value) return
  loading.value = true
  error.value = ''
  result.value = null
  try {
    const res = await fetch('/api/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ header: header.value, titles: titles.value }),
    })
    const body = await res.json().catch(() => ({}))
    if (!res.ok) throw new Error(body.error || `Falha na requisição (${res.status})`)
    result.value = body
    view.value = 'file'
  } catch (e) {
    error.value = e.message || 'Falha ao gerar a remessa.'
  } finally {
    loading.value = false
  }
}

function downloadFile() {
  if (!result.value) return
  const blob = new Blob([result.value.content], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  const stamp = new Date().toISOString().slice(2, 10).replace(/-/g, '')
  a.href = url
  a.download = `CB${stamp}.REM`
  a.click()
  URL.revokeObjectURL(url)
}

async function copyFile() {
  if (!result.value) return
  await navigator.clipboard?.writeText(result.value.content)
}
</script>

<template>
  <div class="shell">
    <aside class="rail">
      <div class="rail__logo" title="cnab-toolkit">CN</div>
      <a
        class="rail__link"
        href="https://github.com/carlosvoliv/cnab-toolkit"
        target="_blank"
        rel="noopener"
        title="Código-fonte"
      >
        <FacetIcon name="code" :size="18" />
      </a>
    </aside>

    <div class="main">
      <header class="topbar">
        <div class="topbar__title">
          <h1 class="title">Gerador de Remessa CNAB</h1>
          <p class="subtitle">Monta o arquivo de largura fixa a partir dos títulos · engine PHP schema-driven</p>
        </div>
        <div class="topbar__tools">
          <FacetChip variant="blue" size="sm">API conectada</FacetChip>
          <div class="theme-pick">
            <FacetSelect :model-value="theme" :options="THEMES" @update:model-value="applyTheme" />
          </div>
        </div>
      </header>

      <div class="workspace">
        <!-- Form -->
        <section class="panel">
          <header class="panel__head">
            <FacetIcon name="edit" :size="15" />
            <span>Dados da remessa</span>
          </header>
          <div class="panel__body">
            <div class="grid-2">
              <FacetInput v-model="header.company_name" label="Cedente / Empresa" />
              <FacetInput v-model="header.company_code" label="Código do cedente" />
              <FacetInput v-model="header.bank_code" label="Banco (nº)" />
              <FacetInput v-model="header.bank_name" label="Banco (nome)" />
              <FacetInput v-model="header.service_code" label="Serviço" />
              <FacetInput v-model="header.file_sequence" label="Sequência do arquivo" />
            </div>

            <div class="titles">
              <div class="titles__head">
                <span>Títulos ({{ titles.length }})</span>
                <FacetButton variant="ghost" size="sm" @click="addTitle">+ Adicionar título</FacetButton>
              </div>

              <div v-for="(t, i) in titles" :key="i" class="title-card">
                <div class="title-card__top">
                  <FacetChip variant="grey" size="sm">#{{ i + 1 }}</FacetChip>
                  <FacetIconButton
                    name="close"
                    label="Remover título"
                    :disabled="titles.length === 1"
                    @click="removeTitle(i)"
                  />
                </div>
                <div class="grid-2">
                  <FacetInput v-model="t.payer_name" label="Sacado (pagador)" />
                  <FacetInput v-model="t.document_number" label="Nº do documento" />
                  <FacetSelect v-model="t.payer_doc_type" :options="DOC_TYPES" label="Tipo" />
                  <FacetInput v-model="t.payer_document" label="CPF / CNPJ" />
                  <FacetInput v-model="t.due_date" type="date" label="Vencimento" />
                  <FacetInput v-model="t.amount" type="number" step="0.01" label="Valor (R$)" />
                </div>
              </div>
            </div>

            <div class="actions">
              <FacetButton :loading="loading" :disabled="!canGenerate" @click="generate">
                Gerar remessa
              </FacetButton>
            </div>
          </div>
        </section>

        <!-- Result -->
        <section class="results">
          <FacetAlert v-if="error" variant="error" title="Erro">{{ error }}</FacetAlert>

          <div v-else-if="!result" class="empty">
            <FacetIcon name="layers" :size="28" />
            <p>Preencha os títulos e gere o arquivo de remessa.</p>
          </div>

          <template v-else>
            <div class="summary">
              <div class="summary__body">
                <div class="summary__head">
                  <span class="summary__eyebrow">{{ result.layout }}</span>
                  <span class="summary__num">{{ totalBRL }}</span>
                  <span class="summary__uuid">
                    {{ result.summary.records }} registros · {{ result.byteLength }} bytes · linha {{ result.lineLength }}
                  </span>
                </div>
                <div class="summary__chips">
                  <div class="info-chip">
                    <span class="info-chip__label">Títulos</span>
                    <strong class="info-chip__value">{{ result.summary.details }}</strong>
                  </div>
                  <div class="info-chip">
                    <span class="info-chip__label">Linhas</span>
                    <strong class="info-chip__value">{{ result.lineCount }}</strong>
                  </div>
                </div>
                <div class="summary__steps"><FacetStepper :steps="steps" /></div>
              </div>
            </div>

            <section class="panel">
              <header class="panel__head panel__head--rec">
                <span class="rec-name">Resultado</span>
                <span class="rec-meta">
                  <FacetButton variant="ghost" size="sm" @click="copyFile">Copiar</FacetButton>
                  <FacetButton size="sm" @click="downloadFile">Baixar .REM</FacetButton>
                </span>
              </header>
              <div class="panel__body">
                <FacetTabs
                  :model-value="view"
                  :tabs="[
                    { value: 'file', label: 'Arquivo gerado' },
                    { value: 'decoded', label: 'Decodificado' },
                  ]"
                  @update:model-value="view = $event"
                />

                <pre v-if="view === 'file'" class="file-out">{{ result.content }}</pre>

                <div v-else class="decoded">
                  <div v-for="(rec, i) in result.records" :key="i" class="rec-block">
                    <div class="rec-block__head">
                      <FacetChip :variant="roleVariant(rec.role)" size="sm">{{ roleLabel(rec.role) }}</FacetChip>
                      <span class="rec-name">{{ rec.name }}</span>
                      <span class="rec-code">tipo {{ rec.code }}</span>
                    </div>
                    <FacetTable :columns="fieldColumns" :rows="rec.fields">
                      <template #cell-span="{ value }">
                        <span class="mono dim">{{ value }}</span>
                      </template>
                      <template #cell-value="{ value }">
                        <span class="mono">{{ value || '—' }}</span>
                      </template>
                    </FacetTable>
                  </div>
                </div>
              </div>
            </section>
          </template>
        </section>
      </div>
    </div>
  </div>
</template>
