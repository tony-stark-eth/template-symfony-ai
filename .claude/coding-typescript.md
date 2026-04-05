# TypeScript Coding Guidelines

## General

- Strict mode (`strict: true` in tsconfig.json)
- `noUncheckedIndexedAccess: true`
- No `any` type — use `unknown` and narrow
- Small focused modules (one concern per file)
- No framework (no React, no Stimulus, no Turbo)

## Build Pipeline

- Compile via Bun: `bun build assets/ts/*.ts --outdir=assets/js/`
- Served via Symfony AssetMapper (importmap)
- No Node/npm, no Webpack, no Encore

## Modules

| Module | Purpose |
|--------|---------|
| `timeago.ts` | Relative time display with `setInterval` (60s), `Intl.RelativeTimeFormat` |
| `theme-toggle.ts` | DaisyUI `data-theme` toggle, `localStorage` persistence |

## Style

- DOM queries typed: `document.querySelector<HTMLElement>()`
- `fetch()` for async operations, no jQuery
- DaisyUI class conventions for UI components
- `data-theme` attribute for theming (`night` dark / `winter` light)
- Event delegation where practical
