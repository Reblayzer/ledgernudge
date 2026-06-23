# Design decisions & the Laravel traps I hit

A short, honest companion to the README. I had not written PHP/Laravel before
this project, so this also documents where the framework surprised me and how I
worked around it.

## Decisions (and the why)

- **Laravel 12 + Inertia + React, not a separate SPA.** The brief allowed either.
  Inertia is the idiomatic Laravel path, ships a maintained official starter kit
  with auth/TypeScript/Tailwind already wired coherently, and keeps the operator
  UI and backend in one deployable. The trade-off is a smaller standalone REST
  surface — but the Stripe and Twilio webhooks are still plain stateless routes,
  so the machine-to-machine surface is still real.

- **Stripe Checkout Sessions, not Payment Links.** Checkout Sessions carry
  per-transaction metadata, so I stamp `invoice_id` into both the session and the
  PaymentIntent and the webhook reconciles back to the exact invoice
  deterministically. Reusable Payment Links would have made that mapping fuzzier.

- **Money in integer cents.** No floats anywhere near money. `outstanding_cents`
  is a derived accessor (`max(0, amount - paid)`).

- **An append-only event log is the source of truth for "what happened and why."**
  The `events` table only ever gets inserts — it carries just `created_at`, and
  the model sets `UPDATED_AT = null`. Every meaningful action (draft, approve,
  send, payment, reply, classification, pause) writes one row. That's what lets
  the operator inbox show an honest timeline.

- **External I/O sits behind seams.** `CheckoutGateway`, `ClaudeMessenger`,
  `SmsGateway`, and `InboundSmsVerifier` are interfaces. The domain logic
  (reconciliation, drafting, sending, classification) is unit-tested against
  in-memory fakes — no live Stripe/Claude/Twilio calls in the suite. The concrete
  adapters that wrap the real SDKs are deliberately thin and are *not* unit-tested,
  because that would need real credentials. This is the main testability decision
  in the codebase.

- **Idempotency where money and webhooks meet.** Stripe event ids are recorded in
  a `stripe_events` table keyed by the id, so duplicate deliveries collide on
  insert and no-op. Payment amounts are written *absolutely* (not incremented), so
  the two events Stripe sends for one successful Checkout converge to the same
  state and log a single domain event.

- **Human-in-the-loop, never auto-send.** Claude drafts → a human approves or
  edits → approval enqueues the send. The human approval is what authorizes
  delivery. There is no code path that sends without it.

- **Bias toward pausing when unsure.** Inbound replies are classified by Claude
  into dispute / promise_to_pay / paid / stop / unknown. Dispute and stop pause
  the sequence per the spec — and so does `unknown`. If the model's reply can't be
  parsed or the category is unrecognised, the classifier falls back to `unknown`,
  which pauses. Better to stop and flag a human than keep nagging someone who
  disputed.

## The Laravel/ecosystem traps I actually hit

- **Composer wasn't on PATH after the official installer.** The `getcomposer.org`
  one-liner ran but left no `composer` on PATH. I reinstalled it to
  `~/.local/bin` with SHA-384 checksum verification and added that dir to PATH.

- **Laravel Breeze is incompatible with Laravel 13's frontend stack.** Breeze's
  React preset is Tailwind-v3-era and produced a `package.json` that mixed
  Tailwind 3 *and* 4 and pinned `@vitejs/plugin-react@4`, which peers Vite ≤7 —
  but Laravel 13 ships Vite 8. `npm install` died with an `ERESOLVE` conflict. I
  pivoted to the **official `laravel/react-starter-kit`**, which is the sanctioned
  Inertia+React+TS+Tailwind path with a coherent, tested dependency set. The
  starter kit pins **Laravel 12** (starter kits lag the framework by a major
  version), which is why this project is on 12, not 13.

- **Inertia + Vite resolve the page component at render time.** The starter kit's
  `app.blade.php` `@vite`s the *specific* page module
  (`resources/js/pages/{component}.tsx`). So a new route 500s with "Unable to
  locate file in Vite manifest" until that page file exists *and* has been built.
  Practically: a feature test for a new Inertia page only goes green after the
  `.tsx` is written and `npm run build` has run.

- **`assertInertia()->component()` looks in the wrong folder by default.** The
  inertia-laravel testing page-finder defaults to `resources/js/Pages` (capital
  P); the starter kit uses lowercase `pages`, so the existence check failed. Fixed
  with a small `config/inertia.php` pointing `testing.page_paths` at the right
  directory (rather than disabling the guard).

- **A constructor dependency dragged Twilio into email-only sends.**
  `MessageSender` depends on `SmsGateway`, so building it to send an *email* would
  construct the Twilio gateway — and Twilio's client throws at construction
  without credentials. I made `TwilioSmsGateway` build the Twilio client lazily
  inside `send()`, so an email send never needs Twilio config.

- **`created_at` isn't mass-assignable, so seeded timestamps were ignored.**
  Backdating the demo timeline by passing `created_at` to `create()` silently did
  nothing; Eloquent set it to "now". The fix was `forceFill([...])->saveQuietly()`
  after creation.

- **A deliberate cross-sprint contract change.** Sprint 3 asserted "approval never
  sends." Sprint 4 made approval *enqueue* a (human-authorized) send. That changed
  the earlier behaviour, so I updated the Sprint 3 test on purpose to assert
  "approval enqueues exactly one send" rather than silently letting it drift.

## Testing strategy & what's stubbed

- **73 tests** run on an in-memory SQLite database (fast, no containers);
  PostgreSQL is the real runtime and every migration is verified against it.
- The thin SDK adapters (Stripe, Claude, Twilio send, Twilio signature) aren't
  unit-tested — they need real credentials — but everything around them is,
  through the seams above.
- **Stubbed on purpose, and why:** the email inbound route trusts its `{from,
  body}` payload (a real deployment would verify the mail provider's signature);
  voice (RetellAI / Telnyx), multi-tenant brand isolation beyond the tone-policy
  field, a real 50-state / GDPR rules engine, and production-grade PII redaction
  are named as the next layers, not built.
