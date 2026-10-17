# Morvarid Learning Core — Phases A + B + C + D + E + F

Standalone WordPress plugin for an **email-free, unified learner identity**.
Version: **0.6.0**. Supports WordPress 6.4+ and PHP 7.4+. The production
website **must use HTTPS**. WordPress administrator login stays separate.

## Installation

This is a separate plugin, **not** a PHP file inside the theme. Copy the entire
`morvarid-learning-core` folder into `wp-content/plugins/` and activate
**Morvarid Learning Core** in WordPress > Plugins. Alternatively, zip this
folder (the zip must contain the top-level `morvarid-learning-core` directory),
then upload it via Plugins > Add New > Upload Plugin.

Do not upload this folder as a theme and do not edit `functions.php`.

Activation and upgrades create eight tables (with the site's configured prefix):

- `<prefix>md_learning_learners`: profile, one globally unique hashed 8-digit code.
- `<prefix>md_learning_links`: long random access-link secrets, stored only as keyed hashes.
- `<prefix>md_learning_sessions`: independently managed, hashed browser sessions.
- `<prefix>md_learning_challenges`: one challenge membership per learner per challenge, with database uniqueness constraints.
- `<prefix>md_learning_responses`: private question answers (draft/final) keyed by learner, educational content and stable question UUID; v0.5.0 adds a manager-visibility flag.
- `<prefix>md_learning_audit_events`: audited administrator answer-detail views, metadata only (no private answer text).
- `<prefix>md_learning_enrollments`: unique central learner/course membership with active or revoked status.
- `<prefix>md_learning_lesson_progress`: unique central learner/course/lesson completion records, preserving timestamps.

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

## Phase F: unified course memberships and lesson progress (v0.6.0)

Requires **Morvarid Learning Core 0.6.0** *and* **Morvarid Daron
theme 1.3.9** for the integrated public course/lesson UI. The course builder
and its `md_course`/`md_lesson` post types remain unchanged; existing lesson
chapter and ordering metadata are honored.

A separate course enrollment model is joined to the Phase A learner identity:

- `md_learning_enrollments` stores a unique learner-course membership
  (active/revoked). `md_learning_lesson_progress` uniquely records
  learner/course/lesson status and completion time; lesson completion is an
  **explicit action**, not triggered just by viewing a video or lesson.
- In the course editor sidebar, administrators may enable
  **اجازه عضویت آزاد با پروفایل مرکزی** per published course. The default
  is **off**, particularly to avoid unintended free access to paid courses.
  There is NO automatic billing or payment verification.
- When open enrollment is enabled, an authenticated learner may agree to
  tracking and enroll exactly once with the existing central identity. Guests
  are taken through central login/registration and returned to the course.
  The one-time newly issued code/link receipt remains available.
- When open enrollment is off, the original course CTA/request-registration
  flow is preserved; the WP administrator can manually add a known learner
  through **یادگیرندگان → عضویت دوره‌ها** after any external approval/payment.
- Administrators can filter enrollments by course ID, manually enroll a
  learner by `L-`ID, revoke, or restore a membership. Revocation stops
  progress writing and removes that course from the learner dashboard; it
  **does not delete** their historical lesson completion records.
- Course pages show the learner's enrollment and actual percentage based
  only on the course's **currently published and correctly linked lessons**.
  Lesson pages allow that learner to mark a lesson complete or incomplete.
  When lessons are deleted, unpublished or reassigned, they are excluded
  from the current progress denominator. No stale completion records are
  transferred to a different course automatically.
- The central personal dashboard has a **دوره‌های من** tab and real course
  summary count, completed/total published lessons, progress bars, course
  links and next unfinished lesson links.
- Every progress write verifies the authenticated central learner,
  published course, active membership, correct published lesson/course
  relationship, and a session-bound CSRF nonce. Submitting another person's
  learner ID or a lesson from a different course cannot alter progress.
- The plugin does **not introduce a lesson paywall or private media access**
  at this phase. Existing published lesson pages remain public as before.
  Course membership is used to track progress, not to enforce paid content
  access. Private-course delivery/payment gating requires a separate feature.
- Course and lesson pages disable shared full-page caching when the plugin
  is active, because personal completion controls and session-specific
  nonces are rendered on the page. The theme still renders the original
  templates when the central plugin is disabled.

### Phase F staging acceptance tests

1. Install plugin 0.6.0 and theme 1.3.9 together. Verify both new database
   tables exist after a WordPress administrator loads the dashboard.
2. Create two published courses with published lessons, and one unpublished
   lesson. Confirm published lesson counts and ordering follow the existing
   course builder.
3. Keep course A's open enrollment disabled. As a learner, verify no free
   self-enrollment is offered. Verify the original registration CTA works.
4. Enable course B's open enrollment. Register a new learner with their
   existing eight-digit code, join B, then join again: only one membership
   record should exist.
5. Complete a lesson, refresh, log out and back in, verify that completion
   and percentage persist; reverse the completion and confirm the timestamp
   becomes NULL and the percentage adjusts.
6. Log in as learner C: try changing another learner's progress, using a
   different course's lesson ID, editing an unpublished lesson, or posting
   without a nonce. All must be refused server-side.
7. In administrator course memberships, enroll an approved learner in the
   closed course, revoke their membership, verify progress cannot be changed,
   then restore it and confirm the previous completion remains intact.
8. Move a lesson between courses in the builder; it must not count toward
   the old course or inherit its completed state in the new course.
9. Check the learner's **دوره‌های من** dashboard list, summaries, progress
   bars and next unfinished lesson links on desktop and mobile.
10. Verify published lesson content is still public: enrollment is not yet
    a paid-content paywall, and no payment provider has been integrated.

Automated CI covers PHP syntax, JavaScript syntax, A–E regressions, and
course enrollment/progress authorization smoke tests. It is not a live WP
browser/MySQL acceptance test, which remains necessary before deployment.

## Phase E: private answer-report administration (v0.5.0)

The WordPress admin receives **یادگیرندگان → پاسخ‌های آموزشی**.
The reporting module is read-only: it never edits, deletes, publishes or
exports learners' answers.

- Real-time counts of answer rows, drafts, final submissions, distinct
  responding learners and distinct educational posts, all reflecting the
  *currently applied filters*.
- Metadata-only paginated list (25 per page) sorted by most recently updated.
  Answer text is not selected in list queries and is not searchable.
- Search by learner display name, educational content title or question
  wording; independent filters for draft/final, numeric learner and content
  IDs, and inclusive start/end date (calendar controls use Gregorian dates,
  stored timestamps are UTC).
- Detail page renders the saved question wording, private answer, learner,
  content, status and timestamps with proper escaping; the answer page is
  never cached.
- Reading a detail creates a metadata-only audit event with the WordPress
  administrator's user ID, learner ID, response ID and UTC timestamp.
  **If the audit event cannot be stored, private detail display is denied**.
- Read permissions use a dedicated WordPress capability:
  `md_view_learning_responses`. This capability is granted to the
  WordPress **administrator role only** during plugin activation/upgrade.
  It is NOT automatically granted to editors, authors or mentors.
  For a specifically authorized WordPress staff account, a site administrator
  may grant this capability through a trusted role/capability-management
  workflow. Such a staff account sees a separate top-level response menu
  if the main learners administration is otherwise unavailable.
- Gutenberg's educational question block now has a **per-block switch**:
  `نمایش پاسخ‌ها برای مدیران دارای مجوز`. It defaults to enabled for
  existing questions. Disabling it excludes the block's existing and future
  answers from manager reports, aggregates and direct-ID detail lookups.
  Answers remain intact in the individual learner's profile. Changes to the
  switch synchronize to answer records when the post is saved. Removing
  a block keeps the previously selected visibility for archived responses.
  Database administrators can still technically access the underlying data.

This feature intentionally does not offer anonymous answer APIs, batch CSV
export, access codes, mentor access based only on challenge assignment, or a
universal answer deletion control. It does not overwrite old user records.

### Phase E staging acceptance checks

1. Upgrade plugin to v0.5.0; log into WP administration once. Check
   `md_learning_audit_events` and the new `manager_visible` column exist.
   Ensure the administrator role has `md_view_learning_responses`.
2. Check the reports menu, filter controls, pagination, summary counts and
   detail view against test draft/final responses. Verify the list never
   contains full answer plaintext.
3. Try the reports page URL and a known detail-ID URL while logged in as an
   ordinary editor or anonymous user: both must return unauthorized.
4. Create a specially authorized staff role with ONLY the response-view
   capability; confirm it sees only the response menu, not learners' access
   codes/credential management.
5. Toggle a question block's manager visibility off, save the educational
   post, verify existing answers disappear from counts and details but remain
   in the learner's own dashboard. Toggle on and verify report visibility
   returns. Check newly saved answers inherit the same setting.
6. Open a detail as an authorized manager and verify an audit event is
   written without answer text. Disable the audit table on a **staging**
   database and verify detail viewing is refused, then restore it.
7. Try malicious search terms, forged numeric IDs, invalid dates and
   an expired/deleted educational post; there must be no unauthorized
   plaintext disclosure or broken SQL.
8. Check HTTPS and private/no-store behavior, and inspect a responsive
   small-screen view.

CI contains PHP/JS lint and isolated PHP smoke tests for role permission
checks, parameterized filters, draft/final reporting, manager-hidden rows and
audit fail-closed behavior. Live WordPress/MySQL and browser testing remains
necessary before production deployment.

## Phase D: editable educational questions and private answers (v0.4.0)

Use the existing Gutenberg editor on **resource/exercise** (`md_resource`),
**podcast** (`md_podcast`) or **lesson** (`md_lesson`) posts. Insert the
**پرسش‌های آموزشی مروارید** block directly between any text, image, audio,
video or download blocks. It can be moved, duplicated or removed using the
regular Gutenberg controls.

Each question block provides:
- Editable title and short introductory text;
- Two independent modes: **reflection** (questions only; never saves answers)
  and **save** (private per-learner answers);
- Any number of independently reorderable questions, with hint/explanation;
- In save mode, short/long answer types and per-question final-only required
  validation; optional manager-controlled editing after final submission;
- **Save draft** and **Submit final** actions, with success confirmation and
  persisted values when reopening the page;
- A personal **پاسخ‌های آموزشی من** dashboard tab with private answer history,
  draft/final badges, edit timestamps and links back to the training content;
- Real saved-answer count on the personal dashboard's home tab.

**Security and storage**

- The question definitions are Gutenberg block attributes stored in the
  educational post content; learner answers are stored ONLY in the new
  `md_learning_responses` database table.
- Stable 128-bit non-secret block/question identifiers are assigned on insert.
  Cloned blocks and duplicate IDs are repaired both in the editor and after
  saving the post. Moving a question or editing its wording does not change
  its ID, so the old answer remains linked; the original question prompt
  is also snapshotted in the private response row.
- On POST, the server reparses the **published** post's current block
  definition and validates the authenticated central learner, the published
  educational content, matching block UUID, nonce and required questions.
  Client-supplied titles, IDs or another learner's ID never authorize access.
- Profile/challenge/question nonces are tied to the learner's independent
  session rather than shared WordPress logged-out nonces.
- Final answers with editing disabled are immutable; if a teacher adds new
  questions to the block, previously final answers stay intact.
- Response pages set no-store/private headers, because they render
  learner-specific answers and session-specific anti-CSRF fields.
- Only the learner who owns an answer may read it from the dashboard or edit
  it on the educational page. Administrators' reporting UI is planned for
  Phase E. Server administrators with direct DB access necessarily still
  have access to the database.
- No email or course enrollment is required. Guests can read reflective
  questions and are invited to sign in for interactive answers.

**Scope:** This is the interactive question block integrated into the
*existing* resource/podcast/lesson Gutenberg editors. The larger academy
redesign (unified content type, independent academy editor/dashboard and
sidebars) is a separate project. Phase D does not replace those editors.

### Phase D staging acceptance checks

1. Install plugin v0.4.0 and load the WP admin once, verifying that
   `<prefix>md_learning_responses` was created by the schema upgrade.
2. Add a question block to a published `md_resource`; alternate reflection
   and save modes, add/reorder/duplicate multiple questions and preview.
3. While signed out, ensure reflection mode displays questions without
   save controls, while save mode prompts central registration/login.
4. Sign in as learner A; save optional or incomplete answers as a draft.
   Refresh, revisit, then submit a final answer. Check required validation
   applies only to final submission.
5. Sign in as learner B in another browser. Confirm B sees no answers from A.
   Change the hidden question UUID or page ID on a POST and verify rejection.
6. Reorder questions and a complete block in Gutenberg. Verify the existing
   answers remain attached to their question UUIDs.
7. Duplicate a block in Gutenberg, save, then verify its new block/question
   UUIDs are distinct, and answers do not cross to the cloned block.
8. Finalize a question with editing disabled. Add a new question to the same
   block and verify the existing finalized answer cannot be changed, but
   the newly added question can be completed.
9. Visit `/learning-dashboard/?md_tab=answers`; check owned responses,
   statuses, timestamps and return-to-content links.
10. Confirm all personalized educational pages have `no-store` headers
    and that URLs contain no answer values or private authentication token.

Automated CI PHP syntax, JS parse and pure-PHP question identity/privacy
smoke tests have been added. Real WordPress, database and browser acceptance
tests still need to be carried out before deployment.

## Phase C: personal workspace (v0.3.0)

The existing `[md_learning_dashboard]` shortcode now renders a full,
responsive, right-to-left personal workspace. All tabs use the same existing
dashboard page; navigation is server-rendered, so JavaScript is not needed:

- `?md_tab=home`: greeting, honest challenge progress metrics, recent
  completed/saved challenge-day events. No invented course or academy data.
- `?md_tab=challenges`: cards for all of the **currently signed-in**
  learner's active mapped challenge memberships. Each includes progress and
  a link to the existing private challenge page.
- `?md_tab=activity`: recent private per-day answer-save and completion
  metadata, capped at 25; never includes private answer text in the list.
- `?md_tab=profile`: display name and immutable learner ID; edit name
  through a CSRF-protected POST. Mapped challenge membership titles are kept
  in sync without touching answers or other identities.
- `?md_tab=security`: list active sessions (created/last seen, 50 maximum),
  revoke an owned session, sign out other devices, or reissue the learner's
  own globally unique 8-digit code and private access link.

Self-service credential rotation invalidates **all** previous codes, access
links and sessions, then immediately opens a new session for the current
browser and displays the new credentials **once** in a 5-minute receipt.
If issuing a new browser session fails after rotation, the learner should
contact an administrator to reissue credentials. No emails are sent.

Both learner identity and session ownership are resolved server-side. A
submitted session ID is always paired with the current learner ID before
deletion. Trying to open someone else's dashboard summary with an arbitrary
learner ID returns no data. Cross-account changes are rejected. Each form
has its own WordPress nonce. Personal dashboards use no-store headers and
should never be included in full-page caches.

The underlying session policy remains 7 days idle / 30 days absolute.
WP administrator sessions are intentionally independent of learner sessions.

**Scope boundary:** the course/progress area (implemented in Phase F) and saved answers from
educational-resource question blocks (implemented in Phase D). Phase C
does not manufacture placeholder progress or change old challenge records.
The optional course/resource links go to their public archive, not a claim
that learner enrollment for those content types is active.

### Phase C acceptance tests (WordPress staging)

1. Open `/learning-dashboard/` signed out; verify no personal data is shown.
2. Sign in as learner A and visit all five tabs. Verify the learner name,
   mapped challenge cards, progress, and recent activity are correct.
3. Sign in as learner B in another browser. Confirm B never sees A's activity,
   answers, sessions, or challenge progress.
4. Change A's display name; verify it persists after signing out/in and is
   reflected in A's challenge membership labels, without changing B.
5. In A's security tab, create sessions in two browsers, revoke one
   session ID, and ensure only that browser is signed out.
6. Use "sign out all other devices" and confirm the present browser stays in.
7. Rotate credentials from A's account; capture the one-time new code/link.
   Verify the old code, links, and both prior sessions are invalid.
8. Try a forged session ID that belongs to B (with A authenticated) and
   verify it returns HTTP 403 without invalidating B.
9. Try a missing/invalid CSRF nonce; verify profile changes are rejected.
10. Verify responsive views on mobile and no private page is cacheable.

Automated PHP lint and pure-PHP smoke tests cover key behaviors. They do
**not** replace a real WordPress/database/browser acceptance test.

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
