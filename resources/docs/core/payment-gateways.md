---
title: Setting Up Payment Gateways
slug: payment-gateways
page: payment-gateways.html
section: Payments
license: extended
keywords: [stripe, paypal, paddle, razorpay, paystack, sslcommerz, coingate, 2checkout, bank transfer, webhook, payment gateway, checkout not working, connect stripe, set up stripe, accept payments, enable a payment method, recurring not working, subscription not activating, webhook not configured, sandbox, test mode, can't enable gateway, payment failed]
---

**Admin → Premium → Gateways** connects the payment processors your store can charge customers through. You can enable more than one at once — customers pick from whichever gateways you've turned on at checkout.

## The available gateways

Nine gateways are supported: **Stripe**, **PayPal**, **Paddle**, **Razorpay**, **Paystack**, **SSLCommerz**, **CoinGate**, **2Checkout**, and **Bank Transfer** (a manual, admin-approved method with no live API). Only **Stripe** and **PayPal** support fully automatic recurring subscriptions — every other gateway, including Bank Transfer, only ever charges one-time payments per billing period, even on a plan configured as monthly or yearly.

## Adding your credentials

Open a gateway and paste in its credentials — each one asks for something slightly different (for example, Stripe wants a Publishable Key, Secret Key, and Webhook Secret; PayPal wants a Client ID, Client Secret, and Webhook ID). You can also set a **Test mode** toggle to use that gateway's sandbox before going live, and a transaction **Fee type/value** if you want to pass a processing fee on to the customer.

## Turning a gateway on

The **Enable gateway** toggle is blocked from switching on until every required credential field is filled in — if you try to enable a gateway with a blank or placeholder credential, you'll see a message telling you to add the required API credentials first. Once enabled, drag to reorder gateways in the list; the order here is the order customers see them at checkout.

## Setting up webhooks

Every gateway except Bank Transfer needs a webhook pointed back at your site so that renewals, cancellations, and failed payments are recorded automatically — without it, a subscription can keep showing as active on your side even after it's actually lapsed on the gateway's side. Each gateway's edit screen shows its exact **Webhook URL** with a copy button; paste that URL into the corresponding webhook setting in that gateway's own dashboard.

## Why checkout isn't completing

- The gateway is enabled but its webhook was never configured on the gateway's own dashboard — payments may go through but subscriptions won't activate or renew correctly.
- The plan being purchased has no Stripe/PayPal ID set for that billing cycle, and the enabled gateway doesn't support one-time-only checkout for it — see [Managing pricing plans](managing-pricing-plans).
- The gateway is still in **Test mode**, so live customer cards are being rejected — check the Mode badge on the Gateways list.
