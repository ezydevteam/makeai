---
title: Setting Up Email Sending
slug: setting-up-email
page: setting-up-email.html
section: Mail
license: regular
keywords: [smtp, email sending, amazon ses, sendgrid, mailgun, postmark, mail driver, from address, from name, send test email, emails not sending, welcome email not arriving, smtp not working, test email fails, email in spam]
---

**Admin → Mail → Settings** connects the service your site uses to send email — welcome messages, login codes, and receipts don't go anywhere without this.

## Connecting your email sending service

Under **Delivery Provider**, choose a Mail Driver — SMTP, Amazon SES, or SendGrid — and set your From Address and From Name. Then fill in the fields for your chosen driver: SMTP needs Host, Port, Encryption, Username, and Password; Amazon SES needs Access Key ID, Region, and Secret Access Key; SendGrid needs an API Key. Once saved, a password or key is stored encrypted and never shown back in full — you only need to re-enter it if you're actually changing it.

## Sending a test email

Under **Connectivity Test**, enter a Recipient Email and click **Send Test Mail**. A successful send confirms your credentials genuinely work end-to-end. If it fails, the error shown usually comes straight from your email provider and names exactly what's wrong.

## Why emails aren't sending

- Test Mail fails with a provider-specific error — that error is the fastest way to diagnose the problem, since it comes directly from SMTP/SES/SendGrid rather than from MakeAI.
- A saved password/API key field looks blank on the settings screen — that's expected, it's stored securely and never shown back in full.
- Check [Mail Logs](email-templates-and-logs) for a specific email's Failed status and error message before assuming the whole system is broken — one email failing doesn't mean the driver is misconfigured.
