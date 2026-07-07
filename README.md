# Conference Tools — User Documentation

User manuals for the **Conference Tools** suite: four Moodle activity
modules that together run a conference from call-for-abstracts through
review, scheduling, and on-site check-in.

*このリポジトリのドキュメントは英語と日本語でご利用いただけます。日本語版は
各マニュアルの `.ja.md` ファイル、または下の表からアクセスしてください。*

## The four activities

| Activity | Purpose | Manual |
|---|---|---|
| **Conference Submissions** (`mod_confsubmissions`) | Call for abstracts — presenters submit talk proposals | [English](mod_confsubmissions.en.md) · [日本語](mod_confsubmissions.ja.md) |
| **Conference Program** (`mod_confprogram`) | Reviewer workflow, decisions, and the published accepted-submissions list | [English](mod_confprogram.en.md) · [日本語](mod_confprogram.ja.md) |
| **Conference Scheduler** (`mod_confscheduler`) | Drag-and-drop time × room block schedule | [English](mod_confscheduler.en.md) · [日本語](mod_confscheduler.ja.md) |
| **Conference Check-in** (`mod_confcheckin`) | Tickets, printed badges with QR codes, on-site check-in, certificates | [English](mod_confcheckin.en.md) · [日本語](mod_confcheckin.ja.md) |

## How the four activities fit together

```
Conference Submissions  →  Conference Program  →  Conference Scheduler
   (call for abstracts)     (review & decide)       (block schedule)
                                    │
                                    ▼
                          Conference Check-in
                       (tickets, badges, check-in)
```

Add all four to the same course. Each depends on the one(s) before it in
the diagram above:

1. **Conference Submissions** collects proposals.
2. **Conference Program** is linked to a Conference Submissions activity in
   the same course; reviewers score and decide on the submissions it holds.
3. **Conference Scheduler** is linked to a Conference Program activity;
   once submissions are accepted, they can be placed on the block
   schedule.
4. **Conference Check-in** is linked to a Conference Program activity too,
   so it can offer presenter-restricted ticket types.

A "favourite"/star marked in Conference Program and a "my timetable" star
marked in Conference Scheduler are the same underlying state — starring a
session in either activity is reflected in the other.

## Guided tours

Each activity ships with built-in **user tours** — short, in-page walkthroughs
that point at the real controls, in English and Japanese, one for organisers
and one for participants. They run automatically the first time someone opens
an activity. Site administrators can import them from the ready-made exports in
[`usertours/`](usertours/) (Site administration → Appearance → User tours →
Import tour).

## Where to start

- **Setting up a conference for the first time?** Read each manual's "For
  organisers" section in the order shown in the diagram above.
- **A presenter, reviewer, or attendee?** Jump straight to your activity's
  "For presenters," "For reviewers," or "For attendees" section.
- **Looking for the plugin source code, installation instructions for
  server administrators, or developer/architecture documentation?** That
  lives in each plugin's own repository, not here — this repository is
  end-user documentation only:
  - <https://github.com/adamjenkins/moodle-mod_confsubmissions>
  - <https://github.com/adamjenkins/moodle-mod_confprogram>
  - <https://github.com/adamjenkins/moodle-mod_confscheduler>
  - <https://github.com/adamjenkins/moodle-mod_confcheckin>

## License

This documentation is released under the same license as the plugins it
documents — GNU GPL v3 or later.

## Author

Adam Jenkins <adam@wisecat.net>
