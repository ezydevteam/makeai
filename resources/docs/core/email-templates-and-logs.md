---
title: Editing Email Templates and Reading Mail Logs
slug: email-templates-and-logs
page: email-templates-and-logs.html
section: Mail
license: regular
keywords: [email templates, email variables, mail logs, resend email, welcome email, otp email, subscription email, edit email template, template variables, resend, email not delivered]
---

**Admin → Mail → Templates** and **Logs** cover the wording of every automated email your site sends, and a record of every email actually sent.

## Editing a template

Every automated email — welcome messages, OTP codes, subscription events, support ticket replies, and more — has its own template with a Slug, Name, Subject, rich-text Content, and an Active toggle. System templates cover the built-in flows and can't be deleted (only edited or disabled); templates you create yourself are fully removable. The editor shows a reference list of variables available for that template's category — things like `{site_name}` and `{user_name}` everywhere, plus category-specific ones like `{otp_code}` for auth emails or `{ticket_number}` for support emails — and an AI Assist option can draft or improve the subject and content for you.

## Reading your mail logs

**Logs** lists every email sent, with its recipient, subject, which template (if any) generated it, and its status — Sent, Failed, or Bounced, with the failure reason shown for failed ones. For any email that came from a template, a **Resend Email** action re-sends it; manually-composed emails (no template attached) can't be resent this way.

## Why an automated email isn't going out

- The template's **Active** toggle is off, which silently skips sending it.
- Your mail driver isn't fully configured — see [Setting Up Email Sending](setting-up-email).
- Check **Logs** for a **Failed** status and its error message before assuming the template itself is broken — most delivery failures are a mail provider issue, not a template issue.
