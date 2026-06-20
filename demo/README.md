# cnab-toolkit — demo

A small full-stack demo with two flows:

- **Gerar remessa** — **generates a CNAB remittance file** from a form, the way
  a securitization/collections back office does: fill the header and the titles,
  hit *Gerar remessa*, download a valid fixed-width file, plus a decoded view
  that round-trips the output as proof.
- **Ler arquivo** — upload a `.REM`/`.TXT` (or paste it) and **parse it back**
  into decoded records.

- **Frontend:** Vue 3 + Vite, styled with [`facet-ui`](https://github.com/carlosvoliv/facet-ui).
- **Backend:** a thin PHP endpoint (`server/index.php`) that drives the real
  `Writer`/`Parser` from the library. It is not part of the published package.

## Run it

From this `demo/` folder, in two terminals:

```bash
# 1) install the library's dev deps once (for the autoloader)
cd .. && composer install && cd demo

# 2) PHP API on :8000
npm run api

# 3) Vite dev server (proxies /api -> :8000)
npm install
npm run dev
```

Open the URL Vite prints (e.g. http://localhost:5173).

## Layouts

The layout dropdown is fed by `GET /api/layouts`, which merges two sources:

- **Public layouts** registered in `server/index.php` (e.g. the didactic
  `generic-remittance-550`).
- **Private layouts** dropped in `server/layouts.local/*.php` — a **gitignored**
  folder for institution-specific maps you can't publish. Each file returns
  `['id' => ..., 'label' => ..., 'layout' => Layout, 'builder' => callable|null]`
  (a `null` builder means parse-only).

This is how you read a real bank/clearing-house file: add its real layout under
`layouts.local/` and pick it in the dropdown. Picking the wrong layout decodes
the wrong bytes — each institution has its own field map, which is the whole
point of the schema-driven design.

Real `.REM` files are usually **latin-1** and byte-positioned, so uploads are
sent as base64 (raw bytes preserved) and decoded values are converted to UTF-8
for display.
