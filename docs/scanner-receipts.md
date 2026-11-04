# Scanner and receipt printing release

Deployed November 4, 2026 (Asia/Manila). Runtime release `e722ae4`, selectively based on `add5fbc`.

## Included

- Exact barcode/SKU lookups are separate from fuzzy search, so an exact item is not displaced by the 30-result search limit. Barcode leading zeroes are preserved; SKU matching is case-insensitive.
- Enter captures each scan immediately and queues lookups. Repeated scans do not disappear during a slow request. Unknown/ambiguous codes and lookup errors show feedback. Manual search and entry remain available without hardware.
- **Ready to scan / type** focuses only the product input. One scan adds one base unit; an existing pack line prompts for manual quantity adjustment. Posting waits for pending scans.
- Posted sales/returns offer **Document / A4**, **58 mm receipt**, **80 mm receipt**, and **Reprint a COPY**. Quantities, pack conversion, original prices/discounts, cash tendered/change, credit due/current balance, reference and internal-record notice are retained.
- **Printer setup & test print** uses synthetic sample data without posting a sale. Browser printing/PDF remains the integration. A failed or cancelled print cannot create or reverse a transaction.
- Administrators can open receipt previews through their audited read-only store view.

This release contains no offline POS routes, service worker, stock reservation code, offline tables, or migration. The offline experiment remains on `codex/scanner-receipts-offline-pilot` and is not part of this production release. No environment or module setting changes are required.

## Setup

Set an optional USB/Bluetooth keyboard-mode scanner to send **Enter** after each code. Focus the product input and review quantities before posting. Other scanner modes are not integrated.

For printing, choose the preview width and matching paper in the OS printer driver. Use 100% scale, minimum margins, and disable browser headers/footers. Save as PDF if no printer is connected. Opening or closing the print dialog does not confirm a physical print. No printer-model certification or BIR/fiscal compliance is claimed.

## Verified locally

- Full PHP suite: **150 tests / 1,312 assertions**, including 6 new scanner/receipt integration tests.
- JavaScript suite: **8 passing tests** covering queued repeats, manual-search races, unknown/ambiguous codes, pack handling, no-device/manual checkout, submit blocking and print failures.
- Pint and PHPStan passed; production frontend build passed.
- No dependency, schema, account, balance, cash or stock-posting service changes.
- Initial checks were repeated after creating the release checkout's Vite manifest; the final complete run passed.

Physical scanner and printer tests remain hardware-specific. The prior local implementation visually verified narrow receipt previews and repeated keyboard entry; this selective release reruns all existing online regressions without offline changes.

## Deployment and rollback

Use the existing Hostinger private Laravel/public_html layout. Before switching, verify the production revision and tracked working tree, preserve its untracked `bun.lock`, take and verify a private recovery snapshot, and briefly put the application into maintenance mode.

Fast-forward to the reviewed release, install its matching `public/build` files, copy `public/receipt.css`, and install the updated public `.htaccess` with `DirectoryIndex index.php`. The static allowlist explicitly permits `receipt.css`; existing private-file protections remain. Keep old hashed assets so open browsers can finish with compatible files. Preserve the production `.env`, key, installed dependencies, public entry point and merchant data. No migrations or seeds are needed.

Rebuild Laravel caches/autoload as required by the current host. Verify owner-scoped exact lookup and all receipt layouts with read-only runtime checks, check protected data fingerprints before/after the release, then leave maintenance and check HTTPS health/login/assets and private-file denials.

For rollback, restore the prior source commit and its public manifest/.htaccess while in maintenance, rebuild caches, and verify before reopening. No database rollback is needed or permitted for this release.

## Production status

Deployed to [Pyesio](https://wgalas.lzrk.io) on November 4, 2026 after a brief maintenance window. Runtime commit: `e722ae4b4d99c82516127885638d9363390b7e0e`. The initial SSH login issue was resolved by removing curly-quote delimiters from the supplied credential when reading it.

- Private recovery snapshot `20261104-151705-245499` was created and verified before release. Archive/checksum verification is not a full restore rehearsal.
- Read-only database transactions verified exact catalog lookup for both WGALAS and LAPITO, plus all three sample print layouts for each store. LAPITO's existing sales record also rendered all three layouts, COPY mode and administrator read-only navigation. WGALAS had no existing sale/return available for that historic-record check; no test sale was inserted.
- Protected table fingerprints matched before/after the release. Production `.env`, public `index.php`, and server-only `bun.lock` hashes remained unchanged. No migrations or seeders ran.
- HTTPS health/login, receipt CSS and released JavaScript return 200. Served CSS/JavaScript SHA-256 hashes match the built artifacts. Both store test-print routes require sign-in; a browser check confirmed the login redirect. Private environment/source URLs return 403.
- Runtime inspection confirmed no offline routes. Old hashed assets remain available. Physical scanner/printer output still requires the store's actual hardware.

Private deployment evidence is retained outside the web root in `.scanner-receipts-20261104` on the host. The runtime uses `app-Cs4PLLpc.js` and the unchanged `app-BDDSCe1L.css`.
