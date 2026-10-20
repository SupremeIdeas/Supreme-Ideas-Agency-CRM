# White Label funnel: release and owner checklist (MASTER ONLY)

Everything below is switched OFF until you turn it on in Admin > Feature switches (`wl_funnel`, `wl_manual_payments`). White labels never receive any of this.

## 1. Load your receiving accounts (nothing is hard-coded)
1. Keep the seed file OUTSIDE the repository (the repo ignores `*.seed.json` and `/seed/`).
2. On the server: `php artisan wl:seed-manual-accounts --file=/path/to/manual-payment-accounts.seed.json` (add `--dry-run` to preview).
3. Banks load as enabled, crypto loads OFF. Account details are encrypted at rest. Re-running never overwrites an edit you made in the admin (use `--update` to force it).
4. Every account, including its "Who the buyer is paying" line, is editable in Admin > White Label payments. When you add your business accounts, edit or replace these rows and change that line. The pay page shows exactly what you wrote there, and nothing when it is empty.
5. Set your legal seller details, number formats and terms in Admin > White Label invoices, and preview a sample PDF there.

## 2. Before going live
- Send a small test transaction on each crypto network, then confirm it in Admin > White Label payments (a crypto account cannot be enabled before that).
- Make sure a fresh rate exists for GBP, EUR, USDT, USDC (live Grey rate, or a manual rate newer than the allowed age).
- Place a test purchase end to end on a phone: Pay now, quote, proof, admin approval, licence email, documents, setup form.
- `php artisan naara:verify wl` must be green.
- Cron: `wl-funnel:expire` (daily), `wl-invoices:retry` (every 10 minutes), `wl-secrets:purge` (daily). They run from the normal scheduler line, `* * * * * php artisan schedule:run`.

## 3. Rollout order
`wl_funnel` first (wizard, wallet payments), then `wl_manual_payments` (USD and GBP first, crypto after its test transaction, EUR after its own test transfer).

## 4. What a buyer and an admin get
Buyer: wizard, pay by wallet or by transfer/crypto with a locked quote, proof upload, status, documents (invoice, receipt, balance invoice for plans that owe a balance), setup form (server login only for own-server hosting).
Admin: payment review (proof beside expected data, checks, approve / underpaid / reject), Funnel tab (status, timeline, WhatsApp link, ask for details, mark complete, audited secret reveal with password and reason), invoice settings. Secrets are erased N days after setup is complete (setting `wl.secrets.purge_days`, default 7).

## 4b. Custom orders (switch `wl_custom_orders`, OFF by default)
Buyer: Account > White Label custom (also linked from the plan step of the wizard): tick what is needed, describe it, optionally budget and timeline; up to 3 open requests. Admin: White Label > Custom orders (hub tab with a count of requests to quote): read the request, price it as lines (the total is summed on the server, up to $100,000, valid 1 to 60 days), decline with a reason, reply in the thread. The buyer accepts, then pays from the wallet (atomic, once, refunded automatically if the order cannot be recorded) or you mark a bank/crypto transfer paid with the reference you checked (audited). Both routes accrue platform revenue once (`wl_custom_order`). Unanswered quotes expire daily (`wl-custom-orders:expire`). No licence is issued by a custom order: it is a service sale you deliver by hand.

## 5. Not built yet
The timed preview and its tour (Phase 2, waiting on your decision). Everything else in the plan is built: gateway (card) payments with the confirming state, custom orders, proforma, credit notes and void, balance invoice reminders (daily 08:10), funnel analytics cards, the funnel check and quote simulator.

## 6. End-to-end run
`scripts/funnel/seed-e2e.php` (roles, plans, flags, rates, receiving accounts, a fresh buyer and an admin) then `ADMIN_PATH=... OUT=... node scripts/funnel/e2e.mjs`: register-free path from the start link through the 7-step wizard, pay by transfer (quote, proforma PDF, proof upload), admin review with its four checks and Approve, then the buyer sees Payment approved and the setup form. Last run: PASS, no console errors.
