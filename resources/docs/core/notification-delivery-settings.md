---
title: Configuring Real-Time Notification Delivery
slug: notification-delivery-settings
page: notification-delivery-settings.html
section: Settings
license: regular
keywords: [notifications, reverb, pusher, polling, websockets, real time, broadcasting, notifications not working]
---

**Admin → Settings → Notifications** configures how in-app notifications reach both admins and customers in real time — not just an admin-only feature, this is the actual broadcasting connection used across the whole product.

## Choosing a delivery driver

**Delivery Controls** sets the **Driver**: **Reverb** (a self-hosted, open-source WebSocket server included with your install), **Pusher** (a third-party hosted WebSocket service), or **Polling Only** (no WebSockets — the browser periodically checks for new notifications instead, on an interval you set: 10s, 15s, 30s, or 60s). Polling works everywhere with no extra setup, at the cost of a short delay before a notification appears.

## Configuring Reverb or Pusher

If you choose **Reverb**, fill in its App ID, App Key, App Secret, Host, Port, and Scheme (HTTP or HTTPS). App ID, App Key and App Secret are the required three — leave Host, Port or Scheme blank and they fall back to `127.0.0.1`, `8080` and `http`. If you choose **Pusher**, fill in its App ID, Key, Secret, and Cluster instead. Either way, use the **Test** button after saving to confirm your credentials are complete for the driver you picked.

Reverb does **not** require Redis. A single Reverb process holds its connection state in memory; Redis is only needed if you run several Reverb nodes that must share that state.

## Why real-time notifications aren't arriving

- The driver is set to **Reverb** or **Pusher** but a required credential is missing — run **Test** to check before assuming the feature itself is broken. When a credential is missing the product falls back to polling automatically, so notifications still arrive, just on the polling delay rather than instantly. The System Health page reports this as a warning and names the missing piece.
- Reverb specifically needs its own process running continuously in the background (`php artisan reverb:start`) — if that process isn't running, WebSocket delivery silently fails and notifications simply never arrive, with no error shown to the user.
- If neither driver is configured correctly, switch to **Polling Only** as a reliable fallback while you sort out the WebSocket setup.
