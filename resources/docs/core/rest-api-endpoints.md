---
title: REST API Endpoint Reference
slug: rest-api-endpoints
page: rest-api-endpoints.html
section: System
license: regular
keywords: [api endpoints, api reference, ai complete, run tool via api, template endpoint, chat api, create chat, list chats, tool catalog api, tools endpoint, reviews api, sse, server sent events, streaming api, generate stream, generate text, estimate cost, idempotency key, documents api, affiliate api, api 419 csrf, streaming not working, api troubleshooting]
---

Every endpoint under `https://your-site.com/api/v1`, with what it accepts and what it
returns. Start with [Using the REST API](rest-api) if you have not created a token yet.

## Public catalog endpoints

No authentication. These return marketplace-safe metadata only — prompt templates never
leave the server. Responses are cached, so a change made in the admin panel appears once
the tool catalog cache refreshes.

- `GET /tools` — every active tool. Add `?category=blog-content` to filter by category slug.
- `GET /tools/categories` — active categories with their tool counts.
- `GET /tools/{slug}` — one tool, including its long-form page content and related tools.
- `GET /tools/{slug}/reviews` — approved reviews, 10 per page, sorted with
  `?sort=recent|helpful|highest|lowest` (default `helpful`). Returns a standard paginator
  plus a `meta.distribution` breakdown of how many reviews gave each star rating. A tool
  with reviews disabled returns 403.

Each tool in the catalog carries a `fields` array describing exactly what it expects — name,
label, type, and whether it is required. Read it to render your form dynamically, then post
those values back as `inputs` when you run the tool. Tools you add later then work in your
integration without a code change.

## AI generation endpoints

These require a Bearer token. All of them are rate limited and billed to the token owner.

**`GET /ai/templates`** lists every active tool with its input fields and access level — a
lighter payload than the public catalog, meant for populating a picker inside your own app.

**`POST /ai/complete`** runs a stateless completion with no tool involved, for when you want
to send your own prompt. It accepts `prompt` (required, up to 10,000 characters),
`system_prompt` (optional, up to 5,000), `provider` (optional — one of `openai`,
`anthropic`, `google`, `xai`, `deepseek`, `openrouter`) and `model` (optional). Provider and
model both fall back to your site defaults. The response returns `content`, `input_tokens`,
`output_tokens`, `model` and `finish_reason`.

**`POST /ai/template/{id}`** runs one of your AI tools. The `{id}` is the tool's **numeric
ID** from the catalog, not its slug. It accepts `inputs` (required — key/value pairs
matching the tool's `fields`), plus optional `provider` and `model` overrides. The tool's
own prompt template, model override and token cap all apply, and the account's plan access
is enforced. The response adds `output_type` and `credits_used` to the usual completion
fields:

```
curl -X POST https://your-site.com/api/v1/ai/template/42 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"inputs": {"topic": "Cold brew at home", "tone": "Friendly"}}'
```

A tool switched off in the admin panel returns 404, so deactivating one immediately breaks
any integration calling it. A tool restricted to a paid plan returns 403 for accounts not on
one, rather than silently downgrading — see [AI Tool Access Control](ai-access-control).

## Chat endpoints

Chats are persistent conversations. MakeAI stores the history and replays it to the model on
each turn, so you only ever send the new message.

- `POST /ai/chat` creates a session, accepting an optional `title` and `model`. It returns
  201 with the chat record — keep the `ulid`, which identifies the chat afterwards.
- `GET /ai/chats` returns the 50 most recently updated chats for the account.
- `POST /ai/chat/{ulid}/message` posts a message and returns the assistant's reply. The path
  takes the chat's **ULID**, not its numeric ID. It accepts `message` (required, up to
  10,000 characters) plus optional `provider` and `model` overrides for that turn. Posting
  to a chat the account does not own returns 403.

The reply comes back as `message` and `role`, with a `tokens` object holding the input and
output counts for that turn.

## Tool review endpoints

Writing a review requires a token, and the rules are deliberately strict — this is what
keeps the ratings on your tool pages trustworthy.

`POST /tools/{slug}/reviews` accepts `rating` (required, 1–5) and `comment` (optional, up to
2,000 characters). The account must have successfully used that tool at least once or the
call returns 422. A tool with reviews disabled returns 403, as does a suspended account.
There is one review per account per tool: posting again edits the existing review, and only
within 30 days of writing it. If review approval is enabled, the review is created
unapproved and stays hidden until an admin clears it, per
[Tool Reviews and Contact Messages](tool-reviews-and-messages).

`POST /tools/reviews/{id}/vote` marks a review helpful or not with `is_helpful` (required,
boolean). Voting is an upsert, so a second call changes the existing vote rather than adding
one, and voting on your own review returns 422.

## In-app endpoints

These authenticate by browser session and CSRF token, not by API token. They power the
MakeAI frontend, and a Bearer token will not authenticate them. They are documented here
because you will meet them when customising the frontend; for a server-to-server
integration, use the AI endpoints above.

**`POST /generate/stream`** returns `text/event-stream` rather than JSON. It accepts `slug`
(the tool slug), `fields` (the input object), an optional `model`, and optional
`refine_content` plus `refine_instruction` for the "improve this output" flow. Events arrive
as `data:` lines and the stream always ends with `data: [DONE]`:

- `{"token": "…"}` — a fragment of output; append it as it arrives.
- `{"reasoning_start": true}`, `{"reasoning": "…"}`, `{"reasoning_end": true}` — thinking
  output from a reasoning model, wrapped so you can render it in a collapsible block.
- `{"usage": {…}}` — the final tallies: input tokens, output tokens, reasoning tokens and
  credits used.
- `{"document": {"id": …, "title": "…"}}` — the saved copy of the output in the user's
  Documents.
- `{"info": "…"}` — the primary provider failed and generation switched to your fallback.
- `{"truncated": true}` — a guest preview was cut short at the public character limit.
- `{"error": "…"}` — generation failed. The message is sanitised, so provider internals are
  never exposed.

MakeAI sends `X-Accel-Buffering: no` on this route, but some proxy and CDN configurations
override it. If output appears all at once at the end instead of word by word, buffering is
the cause.

**`POST /generate/text`** takes the same inputs and returns one JSON response instead of a
stream. Send an `X-Idempotency-Key` header and an identical repeat within 10 minutes replays
the stored result instead of generating — and billing — a second time.

**`GET /generate/estimate`** prices a run before you make it. It takes `slug` (required),
an optional `model`, and an optional `output_length` of `short`, `medium`, `long` or
`very_long`. It returns the estimated credits, estimated tokens, estimated cost in USD, and
the account's current balance. The estimate is clamped to what the tool can actually return,
so a "very long" quote never exceeds the tool's token cap. For an account using its own
provider key the estimate is zero credits and the response is flagged as BYOK.

The remaining session endpoints are `POST /documents`, which saves content to the user's
Documents and takes `slug`, an optional `title`, and `content` up to 200,000 characters; and
the affiliate routes `GET /affiliate` (referral code, link and available balance),
`GET /affiliate/referrals`, `GET /affiliate/commissions`, `GET /affiliate/payouts` and
`POST /affiliate/payouts` for submitting a withdrawal request. All affiliate routes return
404 when the affiliate program is switched off — see [Affiliate Program](affiliate-program).

## Why an API call is failing

- **Every call returns 401** — the token is missing, mistyped, or its row was deleted. The
  header must read `Authorization: Bearer <token>`, including the numeric prefix and pipe
  that are part of the token string.
- **An HTML login page comes back instead of JSON** — the request had no
  `Accept: application/json` header, so Laravel redirected instead of answering with an API
  error.
- **419 on `/generate/*` or `/documents`** — those are session endpoints and the CSRF token
  was missing or stale. Use `/api/v1/ai/*` for external integrations; those need no CSRF
  token.
- **402 on every generation** — the account is out of credits or has hit its daily cap.
- **503 for all users at once** — the site-wide daily AI budget is exhausted. This is a
  safety cap, not a provider outage.
- **429 much sooner than expected** — generation limits count per account across all tools
  combined, and a specific model may carry its own tighter per-minute limit. Read
  `X-RateLimit-Limit` to see the ceiling actually being applied.
- **422 saying the input is too long** — the combined size of `fields` exceeded the maximum
  input characters setting in your AI configuration.

**Admin → AI → Usage Logs** records every API call with its user, model, token counts,
credits and status, including failures. It is the fastest way to confirm whether a call
reached the AI provider at all.
