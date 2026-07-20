---
title: Running Your Support Ticket System
slug: support-ticket-system
page: support-ticket-system.html
section: Communications
license: regular
keywords: [support tickets, help desk, department, canned response, sla, priority, assign ticket, ticket settings]
---

**Admin → Communications → Support** is a full help-desk system: tickets, departments to route them, saved reply templates, and behavior settings like SLA deadlines.

## Working a ticket queue

The ticket list shows each ticket's subject, customer, department, priority (Low/Medium/High/Urgent), status (Open/In Progress/Waiting User/Resolved/Closed), whether it's breaching its SLA deadline, and who it's assigned to. Filter by status, priority, department, or agent; select multiple tickets to bulk-assign, change status, change priority, or delete. Open a ticket to reply, merge, or take other detail-level actions.

## Setting up departments and canned responses

A **Department** has a name, a reply-from email, an optional **Assigned role** (so new tickets in that department auto-route to admins holding that role), a description, and an active toggle. A **Canned Response** is a saved reply template with a title, optional department restriction, and rich-text content — pick one while replying instead of typing the same answer repeatedly; the system tracks how many times each has been used.

## Configuring ticket behavior

Support Settings (opened from the Tickets screen) controls: notifying admins on a new ticket, notifying customers on a reply, enabling a post-resolution satisfaction rating, enabling AI-suggested replies, attachment limits (max count and size, and which file types are allowed), auto-closing resolved tickets after a set number of days, and your **First response** and **Resolution** SLA deadlines in hours.

## Why a ticket wasn't routed or answered in time

- No **Assigned role** is set on the department the ticket came in under, so it isn't automatically routed to anyone.
- The **First response SLA** is set tighter than your team can realistically meet — check the SLA column for tickets flagged as breaching it.
- **Notify admin on new ticket** is off, so no one was alerted when it came in.
