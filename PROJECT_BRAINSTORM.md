# LedgerNudge — tailored project for AgentCollect

**One-line pitch:** An AI-native B2B invoice follow-up tool that drafts respectful dunning messages, sends them over email and SMS, takes payment, and reads the debtor's reply to decide the next step, built in AgentCollect's exact app stack.

## Why this project for this role

AgentCollect is B2B debt collection run by AI agents instead of call centers: Laravel + React app and portals, Stripe for payments and reconciliation, Twilio/Telnyx for telephony, Claude for the negotiation logic, Postgres + Redis + AWS underneath. LedgerNudge is a deliberately scoped slice of that same problem, built in the same stack, so it doubles as proof I ramp fast on a new language (I had not written PHP/Laravel before this) with AI directing the work.

## Posting technologies it demonstrates

- **Laravel (PHP)** — backend API, queue workers, Eloquent models (their "plus, not a gate")
- **React** + **TypeScript** — the operator inbox / dashboard
- **PostgreSQL** — invoices, debtors, messages, events
- **Redis** — Laravel queue + rate limiting for the send pipeline
- **Stripe** — hosted payment links per invoice, webhook reconciliation (paid / partial / failed)
- **Twilio** — SMS reminders and inbound reply capture (email via a simple mailer)
- **Anthropic Claude (Claude API)** — drafts the dunning message in a configurable brand voice, classifies each debtor reply (dispute / promise-to-pay / paid / unsubscribe), and proposes the next action
- **AI-native workflow** — built end to end with Claude Code (custom skills, MCP), plan-first

## Architecture sketch

```
React + TS dashboard  ──>  Laravel API (REST)  ──>  PostgreSQL
   operator inbox            |  Redis queue (jobs)
   draft review/approve      |     ├─ Claude API  (draft message + classify reply)
                             |     ├─ Twilio       (send SMS / capture inbound)
                             |     └─ Mailer       (email sequence step)
   Stripe Checkout link  <───┘
   Stripe webhook  ──> reconciliation (mark invoice paid, stop the sequence)
```

A scheduled worker walks invoices that are past due, asks Claude for the next message in the
sequence given the debtor's history and a per-client tone policy, queues the send (email or SMS),
and waits. Inbound replies and Stripe webhooks feed back in: a reply classified as a dispute pauses
the sequence and flags a human; a Stripe `paid` event closes the invoice and cancels pending steps.
Every step is written to an append-only event log so the operator can see exactly what was sent and why.

## v1 scope

**In:**
- Invoice + debtor CRUD, CSV import of a debtor list
- Configurable dunning sequence (day 0 / 7 / 14) with a per-client tone policy
- Claude drafts each message; operator approves or edits before it sends (human-in-the-loop)
- Send over email and Twilio SMS, with a Redis-backed queue and basic rate limiting
- Stripe payment link per invoice + webhook reconciliation
- Reply classification (dispute / promise-to-pay / paid / stop) with Claude, dispute pauses the sequence
- React inbox: one threaded view per debtor, append-only event log

**Out (v1):**
- Voice calls (RetellAI / Telnyx) — noted as the obvious next layer, not built
- Multi-tenant brand isolation beyond a single tone policy field
- 50-state / GDPR compliance rules engine (stubbed as a per-client policy note)
- Real PII redaction beyond basic masking

## Build plan

1. Laravel skeleton + Postgres schema (invoices, debtors, messages, events) + auth
2. Stripe payment links + webhook reconciliation (the revenue-facing slice first)
3. Claude drafting service + the human-in-the-loop approval step
4. Redis queue + scheduled sequence worker, email then Twilio SMS
5. Inbound reply capture + Claude classification + dispute pause
6. React + TS operator inbox and event log
7. README with the architecture diagram, a seeded demo, and a short "what's stubbed and why" note

Public repo link goes on the CV once the repo exists (likely day one of building).
