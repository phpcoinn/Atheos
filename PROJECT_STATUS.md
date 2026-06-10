# Atheos (PHPCoin Smart Contract IDE) — Project Status

**Project:** `atheos/`  
**Reviewed on:** 2026-06-10  
**Review completed:** 2026-06-10 (smoke test passed, git synced, docs updated)  
**Live URL:** https://atheos.phpcoin.net/

**Status:** **REVIEW COMPLETE — keep running.** Primary web IDE for PHPCoin smart contract development. Maintenance only unless node SC APIs or gateway change.

---

## Overview

**Atheos** is a fork of [Atheos](https://atheos.io) (Codiad-based web IDE), customized for PHPCoin. Developers edit PHP smart contract source in the browser, compile to PHAR, test in a **virtual engine**, and deploy to **testnet** or **mainnet** via the dapps gateway (`sign.php` / `approve.php`).

| Layer | Choice |
|-------|--------|
| IDE shell | Atheos (PHP + Ace editor + file manager) |
| SC panel | Vue 3 (`phpcoin/app.js` + `phpcoin/app.php`) |
| Backend API | `phpcoin/api.php` — JSON RPC-style (`?q=load`, `compile`, `deploy`, …) |
| Node integration | `include/init.inc.php` from co-located node (`SmartContract`, `SmartContractEngine`, `Transaction`) |
| Wallet login | Dapps `legacywallet/auth.php` (mainnet/testnet engines) |
| Signing (real nets) | Redirect to `{node}/dapps.php` → gateway `sign.php` / `approve.php` (`app=Atheos`) |
| Examples | `workspace/examples/demo/*.php` (counter, fund_me, token, etc.) |

**Strategic note:** New ecosystem apps are moving toward **`tx_data` first**; Atheos remains the tool for **classic smart contracts** (types 5/6/7). Deploy flow stores contract payload in **`transaction.data`** / `tx_data` (`signDeploy` → `deployReal`).

---

## Architecture

```
atheos/
├── index.php              # IDE shell; PHPCoin auto-session + per-session workspace
├── config.php             # DOMAIN, DEVELOPMENT, paths
├── phpcoin-sb.php         # Right sidebar: loads node + Vue SC panel
├── phpcoin/
│   ├── app.php            # Vue SC panel markup
│   ├── app.js             # Vue 3 client (compile, deploy, exec, view, connect SC)
│   └── api.php            # SC API (virtual / testnet / mainnet engines)
├── workspace/
│   ├── examples/demo/     # Sample contracts
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

- **`index.php`** — skips Atheos login: `SESSION("user", "user")`; project path = `workspace/users/{session_id}`.
- **Right sidebar** — SC panel via `sb-right.php` → `phpcoin-sb.php` → `app.php` / `api.php`.
- **Theme toggle** — `?toggleTheme` light/dark; PrimeFlex + Vue 3 from CDN.
- **Transactions list** — SQL joins `transaction_data` for SC tx types 5/6/7 (node `tx_data`).

---

## Review outcomes (2026-06-10)

| Item | Result |
|------|--------|
| Live IDE + API | HTTP 200; `freset` returns `status: ok` |
| Wallet login smoke test | **Passed** — `legacywallet/auth.php` path (`d3c7f935`) |
| Local ↔ server ↔ GitHub | **Aligned** at `d3c7f935` after divergence fix (`git reset --hard origin/main` on server) |
| Legacy `phpcoin/actions.php` | **Removed** (unused form-based UI; never on server) |
| Verdict | **Keep running** — no rebuild |

---

## Deployment

| | |
|--|--|
| **Host** | `phpcoin1` |
| **Web root** | `/var/www/atheos` |
| **Domain** | `atheos.phpcoin.net` |
| **Co-location** | Same host as `/var/www/phpcoin-mainnet` (hardcoded in `phpcoin-sb.php`, `phpcoin/api.php`) |

**Deploy:**

```bash
# On phpcoin1
cd /var/www/atheos
git pull --ff-only origin main
php -l phpcoin/api.php
```

**Git:**

| | |
|--|--|
| **Remote** | `https://github.com/phpcoinn/Atheos.git` |
| **Branch** | `main` (server) / `phpcoin-main` (local dev — tracks `origin/main`) |
| **HEAD** | `d3c7f935` — *Fix atheos wallet login to use legacywallet auth path* |
| **Upstream** | `atheos` → `https://github.com/Atheos/Atheos` (reference only) |

---

## Security & hygiene

| Item | Severity | Notes |
|------|----------|-------|
| **No IDE login** | Medium (by design) | Isolated `session_id` workspace per browser |
| **`DEVELOPMENT = true`** in `config.php` | Low | Unminified JS in prod; optional `false` |
| **Hardcoded `/var/www/...`** | Ops | Requires node on same server |
| **`data/users.json`** | Low | Legacy Atheos users; bypassed by auto-login |
| **`workspace/users/`** | OK | Gitignored on server and in repo |
| **Gateway redirects** | OK | Mainnet deploy/exec via dapps sign flow |

---

## Integration with node / docs

- SCE APIs: `getSmartContract`, create/exec fees, view, property, etc.
- **`node/docs/smart-contracts/`** — SC rules; no Atheos IDE link yet (optional).
- **Main site** — no link to atheos in `site/index.php` (optional discoverability).

---

## Optional maintenance (not blocking)

- [ ] Set `DEVELOPMENT` to `false` on production `config.php`
- [ ] Add link on phpcoin.net / node docs → “Smart Contract IDE”
- [ ] Rotate GitHub PAT if still embedded in server `git remote` URL

---

## Related docs

| Doc | Purpose |
|-----|---------|
| [node/docs/smart-contracts/builders-guide.md](../node/docs/smart-contracts/builders-guide.md) | SC authoring rules |
| [node/docs/dapps/tx-data-developer-instruction-manual.md](../node/docs/dapps/tx-data-developer-instruction-manual.md) | tx_data (separate track) |
| [_master-docs/ROADMAP-MATRIX.md](../_master-docs/ROADMAP-MATRIX.md) | Ecosystem planning |

Update this file when deployment, engines, or SC gateway flows change.
