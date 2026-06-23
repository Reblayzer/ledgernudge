# LedgerNudge — Build Plan (Phase 2)

Tailored project for the **AgentCollect** application (Full-Stack AI Engineer, AI-Native).
Built in AgentCollect's exact stack to prove a fast ramp on a new language (PHP / Laravel)
with AI directing the work. Full pitch + architecture sketch in `PROJECT_BRAINSTORM.md`.

**Goal:** a real, inspectable v1 with a public repo and an honest README, finished *before*
the application is sent. The core loop working beats feature completeness.

---

## Integrity rules (do not cross)

- Describe behaviour and stack only. **No invented metrics, no user counts, no "deployed in
  production" or "used by X" claims.** It is a portfolio project, present it as one.
- **Public repo from day one.** Clean, scoped commits (one per sprint), Consentinel-style.
- The README states plainly **what is built vs stubbed**.
- Everything in it must be explainable in a 30-minute founder call. If Claude wrote something
  you can't explain, slow down and understand it before committing.

---

## Environment setup (first session)

WSL Ubuntu 24.04. Node is installed; **PHP and Composer are not yet.**

```bash
# PHP 8.3 + extensions Laravel/Redis/Postgres need
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-common php8.3-pgsql php8.3-redis \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-bcmath unzip

# Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# Laravel app (keep it in a subfolder so PLAN.md / PROJECT_BRAINSTORM.md stay at repo root,
# or at root, decide and note it in the README)
composer create-project laravel/laravel app
```

**Datastores via Docker** (recommended, keeps the machine clean): a `docker-compose.yml` with
Postgres + Redis. **Stripe CLI** for local webhook forwarding. **Twilio** and **Anthropic**
keys live in `.env` only, never committed (`.env.example` documents the shape).

**Architecture decision to make early and record in the README:** Laravel + React can be either
(a) Laravel API + a separate Vite + React + TS SPA (closer to a real client/operator portal
split, more REST surface to show), or (b) Laravel + Inertia + React (more idiomatic Laravel,
faster to wire). Either is honest "Laravel + React". Pick one, say why in the README.

---

## Sprints (one clean commit each)

### 1. Skeleton + schema + auth
- `docker-compose.yml` (Postgres + Redis), `.env.example`, app boots, migrates, README stub.
- Tables: `debtors`, `invoices`, `messages`, `events` (append-only), `users` (operators).
- Eloquent models + relationships + factories + seeders for a demo dataset.
- Basic auth for the operator (Laravel Breeze or Sanctum if SPA).

### 2. Stripe payment links + reconciliation (the revenue slice first)
- Create a Stripe payment link / Checkout session per invoice.
- Webhook endpoint, verify signature, handle `checkout.session.completed` /
  `payment_intent.*`, mark invoice paid / partial / failed, write an `event`.
- Test with the Stripe CLI (`stripe listen --forward-to`). Cover the failed/duplicate webhook.

### 3. Claude drafting + human-in-the-loop approval
- Service that calls the Claude API to draft a dunning message given the invoice, the debtor
  history, and a per-client **tone policy** (a field, not a rules engine).
- Draft is saved as `pending_approval`; operator approves or edits before anything sends.
- Keep prompts in version-controlled files; log token usage. Never auto-send in v1.

### 4. Redis queue + sequence worker + email / Twilio SMS
- Laravel queue on **Redis**; a scheduled command walks past-due invoices and enqueues the
  next sequence step (day 0 / 7 / 14).
- Send over email (Laravel mailer) and **Twilio** SMS. Basic rate limiting on the queue.
- Every send writes an `event`.

### 5. Inbound reply capture + Claude classification + dispute pause
- Capture inbound replies (Twilio inbound webhook for SMS; a simple inbound route for email).
- Claude classifies each reply: `dispute` / `promise_to_pay` / `paid` / `stop`.
- A `dispute` (or `stop`) **pauses the sequence and flags a human**. Store the classification
  + rationale as an `event`. This is the part to get right; bias toward pausing when unsure.

### 6. React operator inbox + event log
- React + TS: one threaded view per debtor, the append-only event log, the approve/edit step.
- Show invoice status, the Stripe link, and the next scheduled step.

### 7. README + architecture diagram + seeded demo + "what's stubbed and why"
- Architecture diagram (the one in `PROJECT_BRAINSTORM.md` is a starting point).
- One-command demo on seed data. A short "design decisions + the Laravel traps I hit" note,
  this is exactly what the AgentCollect founder asked to see.

---

## Definition of done (enough to apply)

Core loop runs end to end on seeded data:
**invoice → Claude draft → human approve → send (email/SMS) → Stripe payment link → webhook
reconcile → reply classified → dispute pauses the sequence.**
Public repo is live, README is honest. Then drop the repo URL into the AgentCollect message
(the `[LEDGERNUDGE REPO LINK]` placeholder) and send.

You do **not** need sprints 6 and 7 polished to apply, but you do need a working core loop and
a README that doesn't oversell. Inspectable and honest beats feature-complete and vague.

## Intentionally out of v1 (name these as the next layers)

Voice (RetellAI / Telnyx), multi-tenant brand isolation beyond the tone-policy field, a real
50-state / GDPR rules engine, production-grade PII redaction.
