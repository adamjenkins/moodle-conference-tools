# Conference Check-in — User Manual

**Activity type:** `mod_confcheckin`
**Part of the Conference Tools suite** ([overview](README.md))

> **Note on current status:** Conference Check-in is still under active
> development. Ticket types, capacity, and eligibility rules described
> below are implemented; the payment/promo-code purchase flow, printable
> badges with QR codes, the check-in scanner, and attendance certificates
> are being finished in upcoming updates. This manual describes the
> activity's intended, complete behaviour so it can be used as a reference
> as each part becomes available — check the "current status" note in each
> section if you're using an in-progress build.

Conference Check-in sells or issues attendance tickets, prints QR-coded
name badges, records check-in at the door, and issues attendance
certificates. A "badge" in this activity always means a **printed name
badge/nametag an attendee wears** — it is unrelated to Moodle's own Open
Badges achievement system.

## Contents

- [For organisers](#for-organisers)
  - [Adding the activity](#adding-the-activity)
  - [Setting up ticket types](#setting-up-ticket-types)
  - [Presenter-only tickets](#presenter-only-tickets)
  - [Promo codes](#promo-codes)
  - [Badge, ticket, receipt, and certificate templates](#badge-ticket-receipt-and-certificate-templates)
  - [Scanning attendees in](#scanning-attendees-in)
- [For attendees](#for-attendees)
  - [Buying or claiming a ticket](#buying-or-claiming-a-ticket)
  - [Your badge and QR code](#your-badge-and-qr-code)
  - [Getting checked in](#getting-checked-in)
  - [Downloading your certificate](#downloading-your-certificate)
- [Frequently asked questions](#frequently-asked-questions)

## For organisers

### Adding the activity

1. Add a **Conference Check-in** activity to your course.
2. Link it to the **Conference Program** activity in the same course, if
   you want to offer presenter-restricted ticket types (see below). This
   link is optional — you can use Conference Check-in without any
   presenter-only tickets and skip this.
3. Save.

### Setting up ticket types

Open **Manage ticket types** to define what attendees can buy or claim.
For each ticket type, set:

- **Name** (e.g. "Full Conference," "Student," "Presenter," "Day 2 Only").
- **Price and currency.** Set the price to zero for a genuinely free
  ticket type — no payment step is involved for a zero-price ticket.
- **Capacity** (optional) — a maximum number of this ticket type that can
  ever be issued. Leave blank for unlimited. Capacity is enforced
  correctly even if many people try to buy the last ticket at the same
  moment.
- **Presenter only** — restrict this ticket type to users who are an
  accepted speaker on at least one submission in the linked Conference
  Program activity (see below).
- **Valid from / until** — an optional access window, for e.g. a
  single-day ticket that should only admit on that day.
- **Visible** — hide a ticket type from the purchase page without deleting
  it (useful once a ticket type sells out or a phase of sales ends).

### Presenter-only tickets

A ticket type marked **presenter only** checks, at the moment someone
tries to buy or claim it, whether they are listed as a speaker (searched
by their Moodle account, not by name) on a submission that has been
**accepted** in the linked Conference Program activity. This check works
regardless of whether Conference Program has been switched to Display
phase yet — presenters can claim their presenter ticket as soon as
they're accepted, without waiting for the public schedule to go live.

*Current status: the presenter-eligibility check and the purchase flow
that uses it are being finished in an upcoming update.*

### Promo codes

Open **Manage promo codes** to create codes that grant a specific ticket
type for free when redeemed, regardless of that ticket type's normal
price. For each code, set which ticket type it grants, an optional
maximum number of uses, and an optional expiry date. A promo-code ticket
never generates a payment receipt, since no payment occurred.

*Current status: promo code management and redemption are being finished
in an upcoming update.*

### Badge, ticket, receipt, and certificate templates

Open **Manage templates** to design the four kinds of document this
activity can produce, using a rich-text editor with placeholder fields you
can insert (attendee name, ticket type, and — for presenters — their
session title/time/room from Conference Program):

- **Badge** — the printed name badge, including a unique QR code per
  attendee once generated.
- **Ticket** — a purchase confirmation / entry pass.
- **Receipt** — generated only for a genuinely paid ticket; never
  generated for a free or promo-code ticket.
- **Certificate** — the attendance certificate, released only after the
  attendee has been checked in (see below).

*Current status: template editing and PDF generation are planned for an
upcoming update.*

### Scanning attendees in

At the event, a staff member with the check-in capability opens the
scanner (usable in a regular browser, or as a mobile-friendly page inside
the Moodle app) and scans each attendee's badge QR code to record their
check-in. Re-scanning an already-checked-in badge is handled gracefully
rather than creating a duplicate record.

*Current status: the scanner and check-in recording are planned for an
upcoming update.*

## For attendees

### Buying or claiming a ticket

Open the Conference Check-in activity and choose from the available
ticket types. A zero-price ticket type, or a valid promo code, issues your
ticket immediately with no payment step. A priced ticket type takes you
through your site's normal payment checkout.

*Current status: this purchase screen is being finished in an upcoming
update.*

### Your badge and QR code

Once you have a ticket, download your badge as a PDF — it includes a
unique QR code that identifies your ticket at check-in. Keep it accessible
on your phone or print it in advance.

*Current status: planned for an upcoming update.*

### Getting checked in

At the venue, present your badge's QR code to a staff member for scanning.
You only need to do this once per event.

*Current status: planned for an upcoming update.*

### Downloading your certificate

After you've been checked in, an attendance certificate becomes available
for you to download from the activity.

*Current status: planned for an upcoming update.*

## Frequently asked questions

**Will I get a receipt for a free ticket?**
No — receipts are only generated for genuinely paid tickets. A free or
promo-code ticket has nothing to issue a receipt for.

**I'm an accepted presenter — do I need to wait for the schedule to be
published before I can get my presenter ticket?**
No — presenter-ticket eligibility is checked against your own acceptance
status directly, which doesn't depend on Conference Program's Display
phase being switched on.

**Is the "badge" the same as a Moodle Open Badge?**
No. In this activity, "badge" always means the printed name badge/nametag
you wear at the event — it has nothing to do with Moodle's own digital
Open Badges achievement system.
