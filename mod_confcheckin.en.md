# Conference Check-in — User Manual

**Activity type:** `mod_confcheckin`
**Part of the Conference Tools suite** ([overview](README.md))

> **Note on current status:** Conference Check-in's full feature set
> described in this manual is implemented: ticket types (including
> group/enrolment auto-grant), capacity/eligibility rules, the payment/
> promo-code purchase flow, printable badge/ticket/receipt/certificate
> templates with QR codes, the check-in scanner (browser and Moodle app),
> and certificate release after check-in.

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
  - [Auto-granting tickets by group or enrolment method](#auto-granting-tickets-by-group-or-enrolment-method)
  - [Promo codes](#promo-codes)
  - [Badge, ticket, receipt, and certificate templates](#badge-ticket-receipt-and-certificate-templates)
  - [Customising what presentationinfo shows](#customising-what-presentationinfo-shows)
  - [Changing the placeholder delimiter](#changing-the-placeholder-delimiter)
  - [Downloading badges, tickets, and receipts in bulk](#downloading-badges-tickets-and-receipts-in-bulk)
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

### Auto-granting tickets by group or enrolment method

When adding or editing a ticket type, the **Auto-grant** section lets you
link it to *either* a course group *or* a specific enrolment method
(not both) already set up in this course. From then on, joining that group
— or being enrolled via that method — automatically issues that person a
free ticket of this type, at no charge regardless of the ticket type's
normal price. This is useful for e.g. comping a ticket to a "Volunteers"
group, or to everyone who enrols via a specific self-enrolment key.

Saving this link also immediately grants a ticket to every current member
or enrolled user, not just people who join afterwards.

If someone later leaves the group, or their enrolment is removed, their
ticket is **left alone** — it is not automatically taken back. To review
and, if appropriate, manually take back a ticket whose granting condition
no longer holds, open **Orphaned tickets** from the ticket types page. It
lists every such ticket along with why it's orphaned (left the group /
no longer enrolled), whether the person has already checked in, and a
**Revoke** action that permanently deletes the ticket (and any recorded
check-in) and frees up its capacity again.

### Promo codes

Open **Manage promo codes** to create codes that grant a specific ticket
type for free when redeemed, regardless of that ticket type's normal
price. For each code, set which ticket type it grants, an optional
maximum number of uses, and an optional expiry date. A promo-code ticket
never generates a payment receipt, since no payment occurred.

### Badge, ticket, receipt, and certificate templates

Open **Manage templates** to design the four kinds of document this
activity can produce, using a rich-text (TinyMCE) editor. Each template
type is edited separately, and starts pre-filled with a simple built-in
layout you can freely rewrite. Insert any of these placeholders anywhere in
a template — they're replaced with the real value when a document is
generated (the exact markers shown below, `[[like this]]`, are this site's
current delimiter — see
[Changing the placeholder delimiter](#changing-the-placeholder-delimiter)):

- `[[fullname]]`, `[[email]]` — the attendee's name and email address.
- `[[tickettype]]` — the name of their ticket type.
- `[[confcheckinname]]` — this activity's own name.
- `[[coursefullname]]`, `[[courseshortname]]` — this activity's own
  course's full and short name.
- `[[origin]]` — how the ticket was obtained (purchased, free, promo code,
  or auto-granted).
- `[[qrcode]]` — the attendee's unique QR code image.
- `[[submissiontitle]]`, `[[track]]` — the attendee's own accepted
  submission title and track, but **only** if they're an eligible
  presenter (linked via the Conference Program activity); these are simply
  left blank for any other attendee, and only ever show their *first*
  accepted submission if they have more than one.
- `[[presentationinfo]]` — like the above, but lists **every** accepted
  submission an eligible presenter is presenting (not just the first),
  one per line. What each line shows is configurable separately for each
  of the four template types below — see
  [Customising what presentationinfo shows](#customising-what-presentationinfo-shows).

Any placeholder you misspell, or one that doesn't apply, is just removed
when the document is generated — it never shows up as literal marker text.

- **Badge** — the printed name badge, including a unique QR code per
  attendee.
- **Ticket** — a purchase confirmation / entry pass.
- **Receipt** — generated only for a genuinely paid ticket; never
  generated for a free, promo-code, or auto-granted ticket.
- **Certificate** — the attendance certificate, released only after the
  attendee has been checked in (see below).

### Customising what presentationinfo shows

Each of the four template types has its own **Presentation info format**
box, right below its content editor. This is a small "template within a
template": whatever you type there is applied once for *each* presentation
a presenter is presenting, and the results are joined with a line break to
build `[[presentationinfo]]`.

It has its own tiny set of markers — `{title}` and `{track}` (single curly
braces, deliberately different from the `[[ ]]`-style markers above, so the
two never get confused) — for example:

```
<strong>{title}</strong> ({track})
```

Leave this box blank to just show the title on its own. A submission with
no track leaves `{track}` blank, so anything you type around it (like the
parentheses above) still shows up empty for that presentation.

### Changing the placeholder delimiter

By default, a template placeholder is written with double square brackets,
e.g. `[[fullname]]`. A site administrator can change the opening/closing
delimiter for the whole site under this activity's admin settings (Site
administration → Plugins → Activity modules → Conference Check-in). This
applies everywhere on the site, not per-activity, so every organiser shares
one consistent convention.

If you change this setting after templates have already been written,
existing templates keep using the *old* delimiter's text, which will no
longer be recognised — you'll need to update those templates to the new
delimiter yourself.

### Downloading badges, tickets, and receipts in bulk

If you hold the bulk-download capability, **Download all Badges**/
**Tickets**/**Receipts** on the activity's main page produces a single ZIP
file containing every attendee's PDF of that type. A deleted user's ticket,
or one whose ticket type was itself deleted, is skipped rather than
failing the whole download; the receipts ZIP only ever includes genuinely
paid tickets.

### Scanning attendees in

At the event, a staff member with the check-in capability opens **Scan
check-in** from the activity's main page (or, inside the Moodle app, the
same screen as a mobile-friendly addon) to record attendees as they
arrive. The scanner always offers a text field that accepts input from a
USB or Bluetooth barcode scanner (which types the code as if typed on a
keyboard) — just scan a badge and its check-in is recorded immediately. If
the device's browser supports camera-based scanning, a "Scan with camera"
button also appears, letting staff point a phone or tablet's camera
directly at a badge instead. Re-scanning an already-checked-in badge is
handled gracefully (shown as "already checked in") rather than creating a
duplicate record, and a badge scanned at the wrong event is clearly
rejected.

## For attendees

### Buying or claiming a ticket

Open the Conference Check-in activity and choose from the available
ticket types. A zero-price ticket type, or a valid promo code, issues your
ticket immediately with no payment step. A priced ticket type takes you
through your site's normal payment checkout. **Your tickets** on the same
page lists everything you've claimed so far, with download links for each.

### Your badge and QR code

From **Your tickets**, download your badge (and, if you like, your ticket
and — for a genuinely paid ticket only — your receipt) as a PDF. Your badge
includes a unique QR code that identifies your ticket at check-in. Keep it
accessible on your phone or print it in advance.

### Getting checked in

At the venue, present your badge's QR code to a staff member for scanning.
You only need to do this once per event — scanning it again is harmless
and won't create a duplicate record.

### Downloading your certificate

After you've been checked in, an attendance certificate becomes available
for you to download from **Your tickets** on the activity's main page. It
isn't available before check-in — trying to download it earlier tells you
it isn't ready yet.

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

**I left the group my ticket was auto-granted from — did I lose my
ticket?**
No. A ticket, once granted, is never automatically taken back. An
organiser can see that your ticket's granting condition no longer holds
(via the "Orphaned tickets" report) and choose to manually revoke it, but
nothing happens automatically.

**Can I get a certificate before I'm checked in?**
No — the certificate is only generated once you've actually been checked
in at the venue. Trying to download it earlier tells you it isn't ready
yet.
