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
| Queue/cache| Redis 7 — queue drives the send pipeline ✅                    |
| Payments   | Stripe (Checkout payment links + webhook reconciliation) ✅   |
| Messaging  | Twilio SMS + Laravel mailer ✅                                 |
| AI         | Anthropic Claude — dunning drafts ✅ + reply classification ✅ |

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

## Payments (Stripe)

Each invoice gets a hosted **Stripe Checkout** payment link, and Stripe webhooks
reconcile the result back onto the invoice.

- `POST /invoices/{invoice}/payment-link` (operator-only) creates a Checkout
  Session for the invoice amount, stamping `invoice_id` into both the session and
  the PaymentIntent metadata, and stores the hosted URL on the invoice.
- `POST /stripe/webhook` (stateless, CSRF-exempt, **verified by Stripe
  signature**) handles `checkout.session.completed`, `payment_intent.succeeded`,
  and `payment_intent.payment_failed`, marking the invoice
  `paid` / `partial` / `failed` and writing a `payment.*` event.

Two correctness details worth calling out:

- **Idempotency.** Every Stripe event id is recorded in a `stripe_events` table
  whose primary key is that id, so a duplicate delivery collides on insert and is
  acknowledged without re-processing. Payment amounts are written *absolutely*
  (not incremented), so even the two events Stripe sends for one successful
  Checkout converge to the same state and log a single domain event.
- **Reconciliation maps back deterministically** via the metadata `invoice_id`,
  falling back to the stored PaymentIntent id.

### Testing the webhook locally

```bash
# Forward live Stripe events to the local app (needs a Stripe account + CLI):
stripe listen --forward-to localhost:8000/stripe/webhook
# Copy the printed `whsec_...` into STRIPE_WEBHOOK_SECRET in .env, then:
stripe trigger checkout.session.completed
```

The automated tests don't need Stripe or the CLI: they post payloads signed with
the same HMAC scheme Stripe uses, so the real signature verification path runs,
and they cover the happy path plus invalid-signature and duplicate-delivery
cases.

## Claude drafting + human-in-the-loop approval

Claude drafts each dunning message; a human approves or edits it before anything
is sent. **v1 never auto-sends** — approval is the terminal state.

- `POST /invoices/{invoice}/draft` builds a prompt from the invoice facts, the
  debtor, recent message history, and the account's **tone policy**, asks Claude
  (default model `claude-opus-4-8`, configurable via `ANTHROPIC_MODEL`) for the
  next message, and saves it as a `pending_approval` message.
- `PATCH /messages/{message}` lets the operator edit the draft body; the message
  stays `pending_approval`.
- `POST /messages/{message}/approve` marks it `approved`. Sending happens in a
  later sprint, behind the Redis queue — there is no send path in v1.

Design notes:

- **Prompts are version-controlled files** (`resources/prompts/dunning/`), not
  string literals buried in code, so the wording is reviewable in diffs.
- **Token usage is logged** — the model and input/output token counts are stored
  on the message and in a `message.drafted` event, so the cost of each draft is
  inspectable.
- The **tone policy is a field, not a rules engine** (a deliberate v1 scope
  choice). It lives on the debtor; in a multi-tenant build it would move to a
  `clients` table. A `ClaudeMessenger` seam keeps the drafting logic unit-tested
  with an in-memory fake — no live API calls in the test suite.

## The send pipeline (Redis queue + sequence worker)

A scheduled command walks the dunning sequence; approved messages go out over a
Redis-backed queue.

- **`dunning:advance`** (scheduled daily) walks past-due, still-outstanding
  invoices, works out the due step by days overdue (**day 0 / 7 / 14**), and
  enqueues a `DraftDunningMessage` job for that step — at most once per step. The
  draft goes to a human for approval (Sprint 3); it is **not** sent automatically.
- **Approving** a message enqueues a `SendMessage` job. Human approval is what
  authorizes delivery — there is still no auto-send.
- **`SendMessage`** delivers over the message's channel: email (Laravel mailer)
  or **Twilio SMS** (behind an `SmsGateway` seam). It marks the message `sent` or
  `failed` and writes a `message.sent` / `message.send_failed` event. The job is
  **rate-limited** (`dunning-sends`, 60/min) and re-checks the message is still
  `approved` at run time, so nothing is double-sent.

Run the worker alongside the app:

```bash
php artisan queue:work          # process the send/draft jobs
php artisan schedule:work       # or rely on cron to run dunning:advance daily
```

Tests use the `sync`/`array` queue and `Mail::fake()` + a fake `SmsGateway`, so
the whole pipeline is covered without Redis, a mail server, or Twilio.

## Inbound replies → classification → dispute pause

The inbound half of the loop: debtor replies come back in, Claude classifies
them, and a dispute (or stop, or anything uncertain) **pauses the sequence and
flags a human**.

- **`POST /twilio/inbound`** — Twilio SMS webhook, **signature-verified**
  (`X-Twilio-Signature` via Twilio's `RequestValidator`, behind an
  `InboundSmsVerifier` seam). Matches the sender to a debtor by phone.
- **`POST /email/inbound`** — a simplified inbound-parse route that accepts
  `{from, body}` and matches by email. (A production deployment would also verify
  the mail provider's signature — out of scope for v1.)
- Each reply is stored as an inbound `received` message and classified by Claude
  into `dispute` / `promise_to_pay` / `paid` / `stop` / `unknown`. The category
  and rationale are written to a `reply.classified` event.
- **Dispute, stop, *and unknown* pause the debtor's sequence** (a `paused_at`
  timestamp + `sequence.paused` event); `dunning:advance` then skips that debtor.
  This is the deliberate **bias-toward-pausing**: if the classifier's reply can't
  be parsed or the category is unrecognised, it falls back to `unknown`, which
  pauses — better to stop and ask a human than to keep nagging someone who
  disputed. (The plan calls for pausing on dispute/stop; treating `unknown` as a
  pause is the safety bias on top of that.)

## Operator inbox (React + TypeScript)

The Inertia/React operator UI ties the loop together in one place:

- **`/inbox`** — a list of debtors with outstanding totals, a "paused" flag when
  a reply paused the sequence, and a count of drafts waiting for approval.
- **`/inbox/{debtor}`** — a threaded view per debtor: every invoice with its
  status, Stripe payment link, and the next scheduled step; the full outbound +
  inbound message thread (with the reply classification); and the **append-only
  event log** down the side. The approve / edit / draft / create-payment-link
  actions are wired here, so an operator can run the whole loop from the page.

The Inertia controllers are covered by feature tests (`assertInertia` on the
component + props); the React build is exercised in CI (`npm run build`).

## What's built vs. stubbed

This is built one sprint per commit. **Inspectable and honest beats
feature-complete and vague.**

- [x] **Sprint 1 — skeleton + schema + auth.** Laravel app boots, migrates on
      Postgres, seeds a demo dataset; the five tables above with Eloquent models,
      relationships, factories, and seeders; operator auth (login / register /
      settings) from the official starter kit. Covered by tests.
- [x] **Sprint 2 — Stripe payment links + webhook reconciliation.** Checkout
      Session per invoice; signature-verified webhook marks invoices
      paid/partial/failed and logs `payment.*` events; idempotent against
      duplicate deliveries. Covered by tests (incl. bad signature + duplicates).
- [x] **Sprint 3 — Claude drafting service + human-in-the-loop approval.**
      Claude drafts each message from invoice + debtor + tone policy; operator
      edits/approves; never auto-sends; prompts version-controlled; token usage
      logged. Covered by tests with an in-memory Claude fake.
- [x] **Sprint 4 — Redis queue + scheduled sequence worker + email/SMS sending.**
      `dunning:advance` enqueues the next due step (day 0/7/14); approval queues a
      rate-limited `SendMessage` that delivers via email or Twilio SMS and logs
      `message.sent`/`message.send_failed`. Covered by tests (fake mail + SMS).
- [x] **Sprint 5 — inbound reply capture + Claude classification + dispute pause.**
      Signature-verified Twilio SMS webhook + a simple email inbound route; Claude
      classifies each reply; dispute/stop/unknown pause the debtor's sequence and
      flag a human (bias toward pausing when unsure). Covered by tests.
- [x] **Sprint 6 — React operator inbox + event-log view.** `/inbox` debtor list
      and `/inbox/{debtor}` threaded view with invoices (status + Stripe link +
      next step), the message thread, the append-only event log, and the
      approve/edit/draft/payment-link actions. Inertia controllers feature-tested.
- [ ] **Sprint 7** — architecture diagram, one-command demo, design notes.

### Intentionally out of v1

Voice calls (RetellAI / Telnyx), multi-tenant brand isolation beyond a single
tone-policy field, a real 50-state / GDPR rules engine, and production-grade PII
redaction. These are named as the next layers, not built.
