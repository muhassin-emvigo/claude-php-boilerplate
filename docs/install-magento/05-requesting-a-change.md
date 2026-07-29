# Requesting a New Requirement or Change

This is for anyone (technical or not) who wants to ask for something new — a new feature, a change to how something works, a bug that needs fixing, or a new business rule.

## Where to add it

Every request gets its own file in the `docs/requirements/` folder.

## How to add one

1. Copy the template below into a new file.
2. Save it in `docs/requirements/` using this name pattern:
   ```
   YYYY-MM-DD-short-title.md
   ```
   Example: `docs/requirements/2026-07-08-add-free-shipping-over-50.md`
3. Fill in each section in plain English — no technical knowledge needed.
4. Let the development team know a new file was added (a message, an email, whatever you normally use).

## The template

Copy everything between the lines below into your new file:

---

```markdown
# <Short title of the request>

**Requested by:** <your name>
**Date:** <YYYY-MM-DD>
**Status:** New

## What do you want?

<Describe in plain language what you want to happen. Example: "Customers who
spend more than $50 should get free shipping automatically at checkout.">

## Why do you want it?

<Explain the reason or business goal. Example: "We want to encourage bigger
orders and match what our competitors already offer.">

## What does "done" look like?

<Describe how you'll know it works. Example: "When I add $50 or more of
products to my cart and go to checkout, the shipping cost shows as $0
automatically, with no coupon code needed.">

## Anything else the developer should know?

<Optional. Any exceptions, examples, screenshots, or related requests.>
```

---

## What happens next

A developer will read your request and follow [04-making-business-logic-changes.md](04-making-business-logic-changes.md) to turn it into a working change, then update the `Status` line in your file (`New` → `In Progress` → `Done`) so you can track where things stand.
