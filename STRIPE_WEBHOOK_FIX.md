# Stripe Webhook Signature Error - Quick Fix

## The Problem

You're seeing this error:
```
[app.ERROR] Stripe webhook: Invalid signature {"error":"No signatures found matching the expected signature for payload"}
```

## Why This Happens

Each time you run `stripe listen`, it generates a **NEW** temporary webhook signing secret. Your `.env.local` file has an old secret, so the signatures don't match.

## The Solution

### Step 1: Check Your Current Stripe CLI Secret

In your terminal where `stripe listen` is running, you should see:
```
> Ready! Your webhook signing secret is whsec_XXXXXXXXXXXX
```

**Copy this exact secret** (it starts with `whsec_`).

### Step 2: Update .env.local

Open `.env.local` and update line 61:
```env
STRIPE_WEBHOOK_SECRET=whsec_PASTE_YOUR_CURRENT_SECRET_HERE
```

**IMPORTANT**: Use the secret from the **currently running** `stripe listen` command.

### Step 3: Restart Docker Container

Clear the cache and restart the PHP container to pick up the new environment variable:

```bash
docker exec symfony_alpine php bin/console cache:clear
docker restart symfony_alpine
```

Or simply restart all containers:
```bash
docker restart symfony_alpine
```

### Step 4: Test Again

Trigger a test webhook:
```bash
stripe trigger payment_intent.succeeded
```

Check the logs:
```bash
docker logs -f symfony_alpine | grep -i webhook
```

You should see:
```
[app.INFO] Stripe webhook received {"type":"payment_intent.succeeded"}
```

## Alternative: Use Stripe Dashboard Webhooks (Production)

For production, instead of Stripe CLI:

1. Go to Stripe Dashboard → Developers → Webhooks
2. Click "+ Add endpoint"
3. Enter your public URL: `https://your-domain.com/api/stripe/webhook`
4. Select events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`
5. Copy the **Signing secret** shown after creating
6. Add to `.env.local`:
```env
STRIPE_WEBHOOK_SECRET=whsec_YOUR_DASHBOARD_SECRET
```

## Current Status

Your `.env.local` currently has:
```
STRIPE_WEBHOOK_SECRET=whsec_f0d5d12550e4bbba836083200edc869b09cf3e913f56699eec4edf07e76967d4
```

**Make sure your `stripe listen` command shows the SAME secret**, otherwise update `.env.local` with the current one.
