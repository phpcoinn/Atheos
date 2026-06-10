# Atheos (PHPCoin Smart Contract IDE) — Project Status

**Project:** `atheos/`  
**Reviewed on:** 2026-06-10  
**Live URL:** https://atheos.phpcoin.net/

**Status:** **Working — keep running.** Primary web IDE for PHPCoin smart contract development. Maintenance mode; no active feature roadmap unless SC tooling changes on the node.

---

## Overview

**Atheos** is a fork of [Atheos](https://atheos.io) (Codiad-based web IDE), customized for PHPCoin. Developers edit PHP smart contract source in the browser, compile to PHAR, test in a **virtual engine**, and deploy to **testnet** or **mainnet** via the dapps gateway (`sign.php` / `approve.php`).

| Layer | Choice |
|-------|--------|
| IDE shell | Atheos (PHP + Ace editor + file manager) |
| SC panel | Vue 3 (`phpcoin/app.js` + `phpcoin/app.php`) |
| Backend API | `phpcoin/api.php` — JSON RPC-style (`?q=load`, `compile`, `deploy`, …) |
| Node integration | `include/init.inc.php` from co-located node (`SmartContract`, `SmartContractEngine`, `Transaction`) |
| Signing (real nets) | Redirect to `{node}/dapps.php` → gateway `sign.php` / `approve.php` (`app=Atheos`) |
| Examples | `workspace/examples/demo/*.php` (counter, fund_me, token, etc.) |

**Strategic note:** New ecosystem apps are moving toward **`tx_data` first**; Atheos remains the tool for **classic smart contracts** (types 5/6/7). Deploy flow already stores contract payload in **`transaction.data`** / `tx_data` (see `signDeploy` → `deployReal`).

---

## Architecture

```
atheos/
├── index.php              # IDE shell; PHPCoin auto-session + per-session workspace
├── config.php             # DOMAIN, DEVELOPMENT, paths (gitignored in upstream; committed in fork)
├── phpcoin-sb.php         # Right sidebar: loads node + Vue SC panel
├── phpcoin/
│   ├── app.php            # Vue SC panel markup
│   ├── app.js             # Vue 3 client (compile, deploy, exec, view, connect SC)
│   └── api.php            # SC API (virtual / testnet / mainnet engines)
├── workspace/
│   ├── examples/demo/     # Sample contracts (in git via exceptions)
│   └── users/{session_id}/  # Ephemeral per-browser workspace (gitignored)
└── components/            # Stock Atheos IDE components
```

### Engines (`phpcoin/api.php`)

| Engine | Purpose |
|--------|---------|
| **virtual** | Local simulation — generated accounts, in-session `SmartContractEngine::process` |
| **testnet** | `node1.phpcoin.net`, chain `01` |
| **mainnet** | `main1.phpcoin.net`, chain `00` |
| **local** | Shown only when `DEVELOPMENT` is true in `config.php` |

### PHPCoin customizations

- **`index.php`** — skips Atheos login: `SESSION("user", "user")`; project path = `workspace/users/{session_id}`; fixed title “PHPCoin Smart Contracts”.
- **Right sidebar** — SC actions panel replaces stock plugin bar (`sb-right.php` → `phpcoin-sb.php`).
- **Theme toggle** — `?toggleTheme` light/dark; PrimeFlex + Vue 3 from CDN.
- **Transactions list** — SQL joins `transaction_data` for SC tx types 5/6/7 (aligned with node `tx_data` storage).

### Legacy / unused

- **`phpcoin/actions.php`** — older form-based SC UI; **not loaded** by current sidebar. Untracked locally; safe to delete or archive after confirming no external links.

---

## Production verification (2026-06-10)

| Check | Result |
|-------|--------|
| `https://atheos.phpcoin.net/` | HTTP 200, IDE loads (Ace, Vue, `phpcoin/app.js`) |
| `phpcoin/api.php?q=freset` | HTTP 200, JSON `status: ok` (node init responds) |
| Security headers | HSTS, X-Frame-Options, etc. from `config.php` `HEADERS` |

---

## Deployment requirements

Atheos **must run on the same host** as the PHPCoin node tree it includes:

| Path (hardcoded) | Used when |
|------------------|-----------|
| `/var/www/phpcoin-mainnet/include/init.inc.php` | Default + mainnet engine |
| `/var/www/phpcoin/include/init.inc.php` | Testnet engine selected |
| `/var/www/phpcoin/tmp/sc/` | PHAR compile/extract temp files |

**Domain:** `atheos.phpcoin.net` (`config.php` → `DOMAIN`).

**Git:**

- Fork: `origin` → `https://github.com/phpcoinn/Atheos.git`
- Upstream: `atheos` → `https://github.com/Atheos/Atheos`
- Branch: **`phpcoin-main`** (local **3 commits ahead** of `origin/main` as of review)
- Last commit: **2026-01-24** — “Updates after testing”

**Deploy (typical):** pull `phpcoin-main` on server web root; ensure PHP 8.x + `phar` extension; nginx vhost to atheos tree; node paths exist.

---

## Security & hygiene

| Item | Severity | Notes |
|------|----------|-------|
| **No IDE login** | Medium (by design) | Anyone gets an isolated `session_id` workspace; not multi-tenant server storage of secrets |
| **`DEVELOPMENT = true`** in `config.php` | Low | Loads unminified component JS; set `false` in production for perf |
| **Hardcoded `/var/www/...`** | Ops | Breaks if node not co-located; document server layout |
| **`data/users.json`** | Low | Legacy Atheos users (`admin`/`user`); bypassed by PHPCoin auto-login |
| **`workspace/users/`** | OK | Gitignored; 100+ local dirs on dev machine only |
| **`Access-Control-Allow-Origin: *`** | Low | In `HEADERS`; review if tightening CORS |
| **Gateway redirects** | OK | Real deploy/exec uses standard dapps wallet sign flow (no private keys in IDE session for mainnet) |

---

## Integration with node / docs

- Uses current SCE APIs: `getSmartContract`, `getSmartContractCreateFee`, `getSmartContractExecFee`, `getSmartContractView`, `getSmartContractProperty`, etc.
- **`node/docs/smart-contracts/`** — builder’s guide applies; **no mention of Atheos IDE** — add cross-link when touching node docs.
- **Main site** — no prominent link to atheos in `site/index.php` (discoverability gap only).

---

## Decision

| | |
|--|--|
| **Verdict** | **Keep running** — official SC IDE for PHPCoin |
| **Priority** | **LOW** — maintenance when node SC APIs or gateway change |
| **Do not** | Rebuild as tx_data app; replace with archived experiments |
| **Phase 2** | None unless Android wallet or web-wallet embeds SC authoring |

---

## Recommended maintenance (optional)

- [ ] Set `DEVELOPMENT` to `false` on production `config.php`
- [ ] Push/sync `phpcoin-main` (3 local commits) to `phpcoinn/Atheos` if intended for server
- [ ] Remove or git-ignore legacy `phpcoin/actions.php`
- [ ] Add link on phpcoin.net / node docs → “Smart Contract IDE”
- [ ] Smoke test: virtual compile → testnet deploy → exec one demo contract
- [ ] Document server vhost path in ops notes (if not already on phpcoin1)

---

## Related docs

| Doc | Purpose |
|-----|---------|
| [node/docs/smart-contracts/builders-guide.md](../node/docs/smart-contracts/builders-guide.md) | SC authoring rules |
| [node/docs/dapps/tx-data-developer-instruction-manual.md](../node/docs/dapps/tx-data-developer-instruction-manual.md) | tx_data (separate from SC IDE) |
| [_master-docs/ROADMAP-MATRIX.md](../_master-docs/ROADMAP-MATRIX.md) | Ecosystem row: “integration check” — **done** with this review |

Update this file when deployment, engines, or SC gateway flows change.
