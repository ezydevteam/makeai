---
title: Using the REST API
slug: rest-api
page: rest-api.html
section: System
license: regular
keywords: [api, rest api, api v1, api token, sanctum, bearer token, personal access token, authorization header, api authentication, api key for customers, integrate, integration, zapier, mobile app, api docs, api documentation, 401 unauthorized, 402 credits, 429 rate limit, rate limit headers, api billing, api credits, curl]
---

MakeAI ships a versioned REST API at `/api/v1`. It lets you or your customers run AI tools,
hold chat conversations, and read the tool catalog from outside the website — from a mobile
app, a WordPress plugin, an automation step, or a script on your own server.

## What the API covers

Every endpoint lives under `https://your-site.com/api/v1`. There are three groups, and the
difference between them decides how you authenticate.

- **Public catalog** (`/tools`) — no authentication. Your tool list, categories, single
  tools, and approved reviews. Safe to call from a browser or a static site.
- **AI endpoints** (`/ai/*`) — authenticated with an API token. This is the real
  integration surface: run a completion, run a tool, create and continue chats, list the
  tools available.
- **In-app endpoints** (`/generate/*`, `/documents`, `/affiliate/*`) — these authenticate
  by browser session and CSRF token, not by API token, because they exist to serve the
  MakeAI frontend itself.

Build integrations against `/api/v1/ai/*`. The `/generate/*` endpoints look similar but
expect a logged-in browser session, so a token will not authenticate them. See
[REST API Endpoint Reference](rest-api-endpoints) for every endpoint in each group.

## Creating an API token

The AI endpoints authenticate with a personal access token. A token belongs to one user
account, and everything that account can do — its plan, its credit balance, its tool
access — applies to every call made with that token.

There is currently no screen where a customer can generate their own token from the
dashboard. Tokens are issued from the server command line. If you intend to sell API access
as a plan feature, that customer-facing screen is something you would add; the token
machinery underneath it already works.

Open Laravel Tinker in the folder containing `artisan`, load the user, and create the token:

```
php artisan tinker

>>> $user = App\Models\User::where('email', 'customer@example.com')->first();
>>> $user->createToken('Zapier integration')->plainTextToken;
```

The string is printed once and cannot be recovered afterwards. Tokens live in the
`personal_access_tokens` table — delete a row to revoke it, and the next request using that
token gets a 401. One user can hold several tokens, which is the clean way to give a
customer a separate token per application.

Treat a token like a password. Anyone holding it can spend that account's credits, so it
belongs on a server you control, never in frontend JavaScript, a mobile app bundle, or a
public repository.

## Making your first request

Send the token in an `Authorization: Bearer` header and ask for JSON back:

```
curl -X POST https://your-site.com/api/v1/ai/complete \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Write a one-line tagline for a coffee subscription box."}'
```

The reply carries the generated text with the token counts and the model that produced it:

```
{
  "success": true,
  "data": {
    "content": "Freshly roasted, delivered before you run out.",
    "input_tokens": 24,
    "output_tokens": 11,
    "model": "gpt-5.4-mini",
    "finish_reason": "stop"
  }
}
```

Always send `Accept: application/json`. Without it, an authentication failure comes back as
an HTML redirect to the login page instead of a clean JSON error, which is confusing to
debug.

## Response format and status codes

Every endpoint answers with the same envelope. Check `success` first: the payload is always
under `data`, and failures explain themselves in `message`.

```
{ "success": true, "data": { ... } }

{ "success": false, "message": "Insufficient credits.", "type": "InsufficientCreditsException" }
```

The status codes worth handling:

- **200 / 201** — success. 201 is returned when a chat session or document is created.
- **401** — the token is missing, malformed, or revoked.
- **402** — the account is out of credits or has hit its daily cap.
- **403** — the plan does not include this tool, the account is suspended, or it does not
  own the chat being posted to.
- **404** — unknown tool ID or slug, or the tool has been deactivated in the admin panel.
- **422** — a field is missing or too long, or the AI provider rejected the request.
- **429** — rate limited; back off and retry.
- **503** — your site-wide daily AI budget is exhausted. Raise it or wait for the reset,
  per [The Daily AI Budget and Spend Controls](daily-ai-budget).

## Rate limits and headers

Generation is rate limited per user account on a sliding window, so one customer's script
cannot starve everyone else. Every response carries the state of the window in
`X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset`. A 429 adds
`Retry-After` and returns a machine-readable body with `"code": "RATE_LIMITED"` and a
`retry_after` value in seconds, so a client can back off without parsing the message.

Out of the box, guests get 5 generations per hour counted per IP, free accounts get 30 per
minute, and accounts on a paid plan get 120 per minute. The per-account limits cover all
tools combined rather than each tool separately.

These are only defaults. Override them per tier in **Admin → System → Rate Limit Rules**, or
give one customer their own ceiling with a per-user override — the usual way to sell a
higher API tier. An individual model can also carry its own per-minute limit, which applies
whenever that model is named in the request. With Redis configured the limiter uses a true
sliding window; without it, MakeAI falls back to a fixed window that resets in steps.

## How API calls are billed

An API call is billed exactly like the same action performed in the browser. There is no
separate API price list.

- Credits come off the **token owner's** balance, derived from tokens consumed multiplied by
  that model's credit rate.
- Every call is recorded in **Admin → AI → Usage Logs** with the user, model, token counts
  and credits charged, so API traffic sits alongside web traffic — see
  [AI Integrations and Usage Logs](ai-integrations-and-usage-logs).
- Daily caps and the global budget both apply. A user over their daily cap gets a 402; if
  the site-wide budget is exhausted, every account gets a 503 until it resets.
- An account using its own provider key costs you nothing — generation runs on that key and
  no platform credits are deducted.
- Failed generations are not charged. A provider error is recorded as a failure, and credits
  are only deducted against real token usage.

Tools can quote their cost before you spend it. The estimate endpoint returns the estimated
credits, the estimated tokens, and the account's current balance — the same call the
frontend uses to warn a user before an expensive generation.
