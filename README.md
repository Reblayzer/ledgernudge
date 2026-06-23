# LedgerNudge

An AI-native B2B invoice follow-up tool: it drafts respectful dunning messages,
sends them over email and SMS, takes payment via Stripe, and reads the debtor's
reply to decide the next step — with a human approving every outbound message.

**This is a portfolio project.** It is built as a deliberately scoped slice of a
real product's problem (AI-run invoice collection) in that product's stack
(Laravel + React + Postgres + Redis + Stripe + Twilio + Claude), to show a fast
ramp on a new language with AI directing the work. There are no users, no
production deployment, and no real money moving through it. The
[“What's built vs. stubbed”](#whats-built-vs-stubbed) section says plainly what
exists today.

The product thinking and full sprint plan live in
[`PROJECT_BRAINSTORM.md`](PROJECT_BRAINSTORM.md) and [`PLAN.md`](PLAN.md).

## Stack

| Layer      | Choice                                                        |
| ---------- | ------------------------------------------------------------- |
| Backend    | Laravel 12 (PHP 8.3)                                          |
| Frontend   | React 19 + TypeScript via **Inertia** (Laravel React starter kit) |
| Database   | PostgreSQL 16                                                 |
| Queue/cache| Redis 7 (queue lands in Sprint 4)                             |
| Payments   | Stripe (payment links + webhook reconciliation) — Sprint 2   |
| Messaging  | Twilio SMS + Laravel mailer — Sprint 4                        |
| AI         | Anthropic Claude (drafting + reply classification) — Sprint 3/5 |

### Why Inertia + React (not a separate SPA)

The brief allowed either a standalone Vite + React SPA talking to a Laravel REST
API, or **Laravel + Inertia + React**. This repo uses Inertia: it is the
idiomatic Laravel path, ships with a maintained official starter kit (auth,
TypeScript, Tailwind already wired coherently), and keeps the operator UI and the
backend in one deployable. The trade-off is a smaller standalone REST surface;
the Stripe and Twilio webhooks are still plain stateless routes, so the
machine-to-machine surface is still exercised.

## Project layout

The Laravel app lives at the **repository root** (not in a subfolder) so a
reviewer can clone and run it with a single `composer install`. The planning docs
(`PLAN.md`, `PROJECT_BRAINSTORM.md`) sit alongside it at the root.

## Getting started

Prerequisites: PHP 8.3, Composer, Node 20+, Docker.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Datastores (Postgres + Redis)
docker compose up -d

# 4. Schema + demo data
php artisan migrate --seed

# 5. Run it (Laravel + Vite together)
composer run dev
```

The demo seed creates one operator (`operator@ledgernudge.test` / `password`),
eight debtors with a mix of open and paid invoices, and an `invoice.created`
entry in the append-only event log for each invoice.

### Tests

```bash
php artisan test
```

Tests run against an in-memory SQLite database (configured in `phpunit.xml`), so
they need no running containers. Postgres is the real runtime; SQLite keeps the
test loop fast.

## Data model (Sprint 1)

Five tables underpin the whole flow:

- **users** — operators who review and approve messages.
- **debtors** — who owes money (name, contact, optional client reference).
- **invoices** — amounts owed, in integer cents; status is an
  `open → partial/paid/failed/void` lifecycle.
- **messages** — outbound dunning messages and inbound replies, with a
  `direction` / `channel` / `status` enum each.
- **events** — an **append-only** audit log. Every meaningful action writes one
  row and rows are never updated or deleted, so the table carries only
  `created_at`. This is what lets an operator see exactly what was sent and why.

## What's built vs. stubbed

This is built one sprint per commit. **Inspectable and honest beats
feature-complete and vague.**

- [x] **Sprint 1 — skeleton + schema + auth.** Laravel app boots, migrates on
      Postgres, seeds a demo dataset; the five tables above with Eloquent models,
      relationships, factories, and seeders; operator auth (login / register /
      settings) from the official starter kit. Covered by tests.
- [ ] **Sprint 2** — Stripe payment links + webhook reconciliation.
- [ ] **Sprint 3** — Claude drafting service + human-in-the-loop approval.
- [ ] **Sprint 4** — Redis queue + scheduled sequence worker + email/SMS sending.
- [ ] **Sprint 5** — inbound reply capture + Claude classification + dispute pause.
- [ ] **Sprint 6** — React operator inbox + event-log view.
- [ ] **Sprint 7** — architecture diagram, one-command demo, design notes.

### Intentionally out of v1

Voice calls (RetellAI / Telnyx), multi-tenant brand isolation beyond a single
tone-policy field, a real 50-state / GDPR rules engine, and production-grade PII
redaction. These are named as the next layers, not built.
