# Morvarid Learning Core — Phases A + B

Standalone WordPress plugin for an **email-free, unified learner identity**.
Version: **0.2.0**. Supports WordPress 6.4+ and PHP 7.4+. The production
website **must use HTTPS**. WordPress administrator login stays separate.

## Installation

This is a separate plugin, **not** a PHP file inside the theme. Copy the entire
`morvarid-learning-core` folder into `wp-content/plugins/` and activate
**Morvarid Learning Core** in WordPress > Plugins. Alternatively, zip this
folder (the zip must contain the top-level `morvarid-learning-core` directory),
then upload it via Plugins > Add New > Upload Plugin.

Do not upload this folder as a theme and do not edit `functions.php`.

Activation and upgrades create four tables (with the site's configured prefix):

- `<prefix>md_learning_learners`: profile, one globally unique hashed 8-digit code.
- `<prefix>md_learning_links`: long random access-link secrets, stored only as keyed hashes.
- `<prefix>md_learning_sessions`: independently managed, hashed browser sessions.
- `<prefix>md_learning_challenges`: one challenge membership per learner per challenge, with database uniqueness constraints.

And three published pages (unless an unrelated page already occupies the slug):

- `/learning-register/`: registration with a name, consent, no email.
- `/learning-login/`: 8-digit code login, no attempt limit or lockout.
- `/learning-dashboard/`: private learner dashboard and logout.

If a page slug is already in use, create a page manually using one of the
shortcodes below, then update the page mapping option or remove the slug
collision before reactivation. On plugin activation the page IDs are stored
in `md_learning_core_pages`.

Shortcodes: `[md_learning_register]`, `[md_learning_login]`,
`[md_learning_dashboard]`.

The separate WordPress administration menu **یادگیرندگان** allows an
administrator to create learner profiles, issue a fresh 8-digit code and direct
link, disable access, and see registration metadata. Issued code and link
appear **once**. The code is not recoverable from the database; reissuing
invalidates prior code, links and sessions without deleting the learner record.

## Authentication architecture

- An 8-digit code belongs to the learner, globally across the future academy,
  challenges, and courses. Leading digit is 1–9 to keep exactly eight digits.
- Code hashes use HMAC-SHA256 keyed by the WordPress auth salt and have a
  database unique index. Plaintext codes are not stored persistently.
- Direct links use a cryptographically random 256-bit token, whose keyed hash
  is stored. The link is exchanged immediately for a browser session on a
  server-side redirect, and the token disappears from the address bar.
- Learner browser sessions use a host-only HttpOnly, SameSite=Lax cookie,
  Secure on HTTPS. In production HTTPS is required to sign in.
- A session expires after 7 days of inactivity or 30 days total.
- Personal pages and link exchange use no-store and noindex headers.
- Management actions are restricted to WordPress administrators with
  `manage_options`, and all write forms use CSRF nonces.
- Register/create credentials are displayed for 5 minutes in an **ephemeral,
  one-read WordPress transient** (they are otherwise irrecoverable), and
  removed after the first successful view. Protect your database backups
  and object cache accordingly.
- Entry point for future integrations:
  `md_learning_current_learner_id()` (0 means signed out) and
  `md_learning_is_authenticated()`.
- This identity is **not** a WordPress user account. Never authorize access
  to answers using a user-supplied learner ID. Always resolve it from the
  validated server-side session.

## Phase B: challenge integration (v0.2.0)

**Both the theme and the plugin must be upgraded together**. A plugin update
without the corresponding updated theme does not activate the central
challenge UI. The plugin schema migrates automatically on activation / the
next administrator request and creates `md_learning_challenges`.

The independent challenges still use `md_challenge`, `md_challenge_day`,
`md_challenge_member` and `md_challenge_entry`. Published challenge pages
now recognize the *central learner session* and display the corresponding
private challenge dashboard with entries and progress. A new enrollment creates
a private member post, then creates a unique central learner-to-challenge
mapping. **No extra challenge-specific code or link is generated.**

- At a challenge, logged-out visitors see **central login and register**
  options. The chosen challenge is carried through sign-in; new registrants
  see their one-time access code receipt before returning to the challenge.
- Logged-in learners may join a challenge after consenting to store progress.
  Joining twice is idempotent and cannot create two mapped memberships.
- On return, the central session directly displays that learner's days,
  answers, notes, and completed-day count.
- Saving an entry validates the central session's membership server-side.
  Neither a posted `member_id` nor an old challenge key can override it.
- The central dashboard shows the learner's linked challenge cards with
  computed completed-days and progress percentages.
- Editors still see the challenge admin reporting panel. In central mode,
  it shows **only centrally enrolled participants**. WordPress administrators
  can enroll a known `L-123` profile by its numeric ID; challenge managers
  may review answers and revoke/reactivate membership.
- Historic challenge-only codes, keys, and link-based login are **obsolete**
  while the central plugin is active. Their public form endpoints return
  HTTP 410. Existing participant posts remain in the database but are not
  automatically linked or displayed in the central dashboard. The user
  explicitly approved this clean break and does not require old credentials.
- If the independent learning plugin is disabled, the theme still supports
  the original legacy challenge UI as a compatibility fallback.
- Course access and educational-response blocks are out of scope for
  Phase B and remain future phases.

`md_learning_current_challenge_member($challenge_id)` returns the current
learner's authorized challenge member or null. Always use the central
session when reading/writing participant answers; never trust an arbitrary
member ID or historical bearer link.

### Phase B acceptance tests (staging)

1. Upgrade the theme **and** the plugin; visit WordPress administration to
   complete schema upgrade to 0.2.0. Verify the fourth mapping table exists.
2. Register a brand new learner from a challenge page. Record the 8-digit
   code from the dashboard receipt, and follow the link back to the challenge.
3. Join that challenge; confirm the welcome page uses the learner's name,
   and no second code or private challenge link is issued.
4. Write answers and notes, check a day complete, and save. Log out, log in
   with the same central code, return to the challenge, and confirm the
   persisted answers and the correct completion count.
5. Join a second challenge under the same central profile; verify the central
   dashboard shows **both** challenge cards with independent progress.
6. Verify anonymous requests cannot access any participant answers. Modify
   a POST form's `member_id` to another user's ID and confirm HTTP 403.
7. Verify old challenge-only code login and old registration POST endpoints
   return HTTP 410, and the old `md_member/md_key` URL cannot reveal
   someone else's answers.
8. In admin: assign an existing central learner by `L-`ID to a challenge,
   review answers, revoke and restore membership; verify immediate effect.
9. Check that the dashboard and challenge responses send `no-store` headers
   so personalized HTML is not cached.
10. Deactivate only the learning plugin on a staging clone to verify theme
   fallback. Reactivate before restoring production traffic.

**Important:** The automated CI syntax/contract checks are not a substitute
for exercising these flows against an actual WordPress database.

The site owner requested **unlimited code-login attempts**. This carries a
real online code-guessing risk and is unsuitable by itself for especially
sensitive responses. Unlike the long private link, eight digits are not a
high-entropy authenticator. Access logs, automated protections imposed by
the host/CDN, and HTTPS should still be maintained.

## Phase A acceptance test

1. Activate the plugin over HTTPS; verify all three pages, tables and menu.
2. Register a learner and **copy code and link from the one-time receipt**.
3. Open an incognito browser: enter the eight-digit code (Persian and
   Arabic-Indic digits are accepted). Confirm you see the same learner ID.
4. Log out and confirm the dashboard does not reveal the profile.
5. Open the direct private link: confirm it redirects to the clean dashboard
   URL with no token left in the address bar.
6. In admin, rotate credentials. Verify both old code and old link fail, but
   the new ones work; prior active sessions are invalidated.
7. Revoke access. Confirm all credentials cease to work.
8. Check no email, phone number, or WordPress user registration is requested.
9. Verify no login attempt counter or lockout exists. **This is a stated
   acceptance requirement with a corresponding security trade-off.**
10. Check current challenge pages still function independently.
11. Repeat with a non-admin WordPress account; make sure it cannot see or
    modify any central learner records.

**Note:** Source code and syntax checks are not a substitute for installing
and running these steps on a WordPress staging site.
