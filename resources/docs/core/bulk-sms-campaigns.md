---
title: Sending Bulk SMS Campaigns
slug: bulk-sms-campaigns
page: bulk-sms-campaigns.html
section: Marketing
license: regular
keywords: [bulk sms, sms campaign, text message, text customers, sms marketing, mass text, sms blast, sms recipients, sms gateway, twilio, vonage, messagebird, sms opt in, phone verified]
---

**Admin → Marketing → Bulk SMS** sends a one-off text message to a list of opted-in customers, with a delivery report you can track and retry.

## Composing a campaign

Pick recipients from the list, write your **Message** (up to 1,000 characters), and optionally add an **Action link** with custom **Link text** — the link is appended to the message on its own line. A live preview shows the exact text that will be sent, its character count, how many SMS segments it takes (GSM-7 messages fit 160 characters per segment; adding an emoji or non-Latin character switches to UCS-2, which drops the budget to 70 per segment and costs more), and the total segments across all recipients — this is what your SMS gateway bills you for. Click **Send SMS** and confirm; the campaign is then queued and sent in the background, so leaving the page won't interrupt it.

## Who can be selected as a recipient

The recipient list only shows users who meet both conditions:

- They have a **verified phone number**.
- They've turned on **Receive SMS marketing** in their own account's Privacy settings (this is on by default, but a user can switch it off, and it also flips off automatically if they revoke their phone verification).

A user who doesn't meet both isn't selectable, so you can't accidentally text someone who never verified a number or explicitly opted out.

## Reading campaign history and delivery status

The **Recent campaigns** table on the same page lists your last 25 campaigns with their status, a sent/total count, and a failed count. Open **Details** on any campaign to see the per-recipient log — name, phone number, delivery status, and the exact error message for any failure. If a campaign has failures, a **Retry failed** action appears (on both the list and the detail page) that re-queues only the failed recipients, leaving already-delivered ones untouched.

## Why sending isn't working

- No SMS gateway is configured yet — Bulk SMS shares its provider connection with **Settings → Extensions → SMS Gateway**; see [Connecting Third-Party Services](oauth-and-extensions). Until a provider (Twilio, Vonage, or MessageBird) is enabled and its credentials saved there, the Bulk SMS page shows a warning banner and the send button is unavailable.
- The recipient list is empty — no user yet has both a verified phone and SMS marketing opted in; this isn't something you can override from the admin side.
- A specific recipient shows a failed status — open that campaign's **Details** and read its error message; it comes straight from your SMS provider (e.g. an invalid number, an unreachable carrier, or an exhausted account balance) and isn't something MakeAI itself controls.
