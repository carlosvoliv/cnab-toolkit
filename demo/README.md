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

## Note on parsing real files

The demo ships the generic `GenericRemittance550` layout, whose field positions
are illustrative — they are **not** any institution's real map. Parsing a real
file therefore requires declaring that file's actual layout (which is exactly
the point of the schema-driven design); pasting a foreign file into the generic
layout will misread fields. Generation, by contrast, is fully self-consistent.
