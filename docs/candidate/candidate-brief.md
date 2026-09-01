# Technical Architect Take-Home — Seller Return & Refund

> **Fictional-framing note.** The company, orders, users, external system, identifiers, and repository history in this exercise are fictional. The external system is represented by a local API stub. No vendor account, commercial extension, external service, credential, or production information is required, or expected.

This is a single document in three sections:

1. **Candidate Brief** — what to do, how to submit, and how your work is assessed. Start here.
2. **Functional Requirements (FRD)** — the inherited functional spec you will design from.
3. **Architecture Overview** — the inherited design you will review and extend.

The Functional Requirements and the Architecture Overview are working documents handed to you as they are. (Note: the numbered **Part 1 / Part 2 / Part 3** inside the Brief are the three *tasks* you perform; the three *sections* of this document are named, not numbered, to keep the two distinct.)

---

# Candidate Brief

## Purpose

Welcome, and thank you for the time you are about to spend on this. This assignment assesses how you work through an unfamiliar Magento subsystem: understand the domain, review proposed changes, turn a functional spec into a design, evaluate an inherited design, and implement one material improvement. It is the everyday architect loop — review, judge, extend, execute — as one continuous piece of work.

The company, orders, users, external service, identifiers, and repository history in this exercise are fictional. The external system is represented by a local API stub. No vendor account, commercial extension, external service, credential, or production information is required, or expected.

**On AI.** Using AI is expected and encouraged. We are not trying to catch AI use — use whatever tools you like, and tell us how you used them (see the AI-usage log below). We read your judgment, not your keystrokes, so the parts of this that only judgment can do are where your time is best spent.

## Time and working model

- Allow approximately **eight hours of focused work**.
- Submit within **twenty-four hours** of receiving the repository.
- The exercise is set at the limit of that budget on purpose. A tight, correct submission beats a large, sprawling one everywhere here.
- The time allocation is yours. It is acceptable to leave lower-priority work unfinished when the trade-off is explicit and defensible — if you stop before completing something, state where you stopped, why, and what you would do next.

## Supplied environment

Setup is deliberately trivial. If any step seems to need an account, a credential, or an external service, it is a misread — re-check the README.

Prerequisites:

- Docker with Compose v2;
- Git; and
- at least 8 GB of RAM available to Docker.

From the repository root, run:

```bash
docker compose up
```

The image is pre-baked. Wait for the assignment-ready health signal, then use the local URLs and synthetic credentials listed in the repository README. When the stack reports healthy, the store, the admin, and the ERP Refund API stub are all up. You should never be blocked on environment setup; "I could not get it running" is not an outcome we expect.

If the stack does not become healthy, capture the failing service, its health output, and the smallest useful log excerpt. Do not spend the assignment rebuilding the base environment.

### Technical baseline

| Area | Supplied baseline |
| --- | --- |
| Application | Magento Open Source 2.4.7-p10 |
| PHP | 8.3 |
| Database | MariaDB 10.11 |
| Search | OpenSearch 2.12 with ICU and Kuromoji analysis plugins; Magento core OpenSearch adapter |
| Cache and session | File-backed in the local assignment runtime |
| Message broker | No AMQP broker is configured; durable async work uses a DB outbox with cron/CLI consumers |
| Stub runtime | Node.js service for the ERP Refund API, pre-baked into its container |
| Store | `ja_JP`, JPY, `Asia/Tokyo` |
| Theme | Child theme derived from `Magento/blank` |

The exercise deliberately uses Magento Open Source and neutral, builder-authored components. Do not assume the availability of commercial-edition features, separate B2B packages, commercial search extensions, vendor connectors, or a cloud-specific deployment layer.

## The domain in one paragraph

A Magento Open Source store sells a catalogue in which some products are third-party **seller** items and some are **first-party**. When a delivered order that contains seller items has a problem, a Customer Support operator processes a full or partial (line-item, quantity-based) refund through a custom Admin screen. A separate Finance team performs the actual bank transfer offline and later registers its reference and date. The store notifies an external company, **ERP** — the seller-settlement system of record — through the **ERP Refund API**:

| Action | Local contract | Purpose |
| --- | --- | --- |
| Create Refund | `POST /erp-api/v1/refunds` | Register the refund with ERP and return an `erp_refund_id` |
| Read Refund Status | `GET /erp-api/v1/refunds/{erp_refund_id}` | Read the ERP-side refund state |
| Confirm Refund | `POST /erp-api/v1/refunds/{erp_refund_id}/confirm` | Confirm completion after the offline cash refund is recorded |

These three actions are served entirely by a **local stub**, including their failure cases (timeouts, rate limiting, and error responses), so you can exercise every integration path without a live system. The company, ERP, identifiers, and data are synthetic; no knowledge of any external marketplace product is expected. The full request, response, status, and failure semantics live in the supplied functional requirements and are implemented by the stub.

## Your assignment

One codebase, one domain, three connected parts. You absorb the domain once, then work within it: the build inherits the reading you have already done, and the architecture review reuses the same subsystem.

### Part 1 — Review two proposed changes

Review both supplied Git branches against `main`:

- `review/pr-01-partial-refund-presentation`
- `review/pr-02-erp-refund-sync`

Read each change description, the diff, the surrounding code, the tests, the callers, and the useful Git history. Do not limit the review to the lines immediately changed.

For every issue you raise, make clear how serious it is and why, the evidence behind it, and what you would do about it — and leave the review comment you would actually send the author. We read that comment: delivering hard feedback well, professionally and without condescension, is part of the role.

State the criteria you used to rank your findings. Volume is not the goal.

### Part 2 — Design the flow and review the architecture

**2A — Design the refund flow.** Using the **Functional Requirements (FRD)** section below, design the end-to-end refund flow you would build. Produce:

- **a sequence diagram** of the end-to-end refund flow, including the important failure boundaries; and
- **a phased delivery plan** at epic granularity, showing dependencies and the major acceptance gates. Epics, not tickets.

Bound the diagram and the plan deliberately so they do not consume the day.

**2B — Review and extend the architecture.** Read the **Architecture Overview** section below as a proposed design approaching implementation sign-off. Review it for the risks that would matter at sign-off, ranking them by blast radius with your criteria stated and proposing a practical response to each. Then write a concise **target-state extension** describing how you would take the subsystem to production, why the changes are needed, and how you would validate them. Finish with the facts you would verify and the questions you would ask before signing off. You need not select a particular infrastructure product where the guarantee can be stated independently of one.

### Part 3 — Implement one high-value fix

Take the most serious defect you identified in Part 1 and fix it properly in the running module. Your change must include an automated test that demonstrates the defect and proves the corrected behaviour.

Add a short **impact note** explaining the change and its effects. The supplied application and bounded test suite must continue to run through the containers.

## What you submit

Commit your work to a private or access-controlled Git repository created from the supplied assignment repository. Do not add credentials, generated secrets, database volumes, or any material not supplied to you. Your code and test may stay in the normal module and test directories; in the root README, link to the implementation commit and record the exact test command.

Place the written work under `submission/`:

```text
submission/
  01-code-review.md
  02-refund-design.md
  03-architecture-review.md
  04-build-impact-note.md
  05-ai-usage.md
  06-questions-and-next-steps.md
```

- **`05-ai-usage.md`** — a candid account of how you used AI. We value a candid, useful account over a claim of minimal use; it is framed as transparency and is not held against you.
- **`06-questions-and-next-steps.md`** — your open questions, the assumptions you made, where you deliberately stopped, and what you would do next. Sharp questions and honest prioritisation are signals we value.

Before submitting, verify from a clean clone that the documented startup and test commands work.

## The discussion round

A **seventy-five to ninety minute discussion** follows the submission. We will walk through your review and design, ask you to defend specific decisions, and run a short live extension of the work. Its purpose is to confirm you understand what you submitted — whoever or whatever helped you produce it. Come ready to explain your reasoning, not to recite your document.

## Ground rules

- **Use AI freely, and tell us how.** We read judgment, not keystrokes.
- **You need nothing but this repository and Docker.** No accounts, no credentials, no external services.
- **Precision over volume.** A tight, correct artifact beats a large one everywhere here. When in doubt, cut.
- **State your assumptions, and reason from evidence.** Where you make a call, say what it rests on and how confident you are, and keep your reasoning traceable.

Good luck. We are looking forward to reading your work.
