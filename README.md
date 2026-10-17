# parvanesho-theme

## Theme

The active WordPress theme is in `morvarid-daron-theme/`.

## Unified learner identity — Phases A + B + C + D + E + F

A new **separately installable WordPress plugin** is in
[`morvarid-learning-core/`](./morvarid-learning-core/README.md).

This plugin is **not automatically installed by updating the theme**.
To use Phases A–F, copy the `morvarid-learning-core` directory to
`wp-content/plugins/`, activate **Morvarid Learning Core**, and open:

- `/learning-register/` — create learner identity with name only.
- `/learning-login/` — sign in with global eight-digit access code.
- `/learning-dashboard/` — personal identity dashboard.

WordPress administrators get a separate **یادگیرندگان** menu for issuing and
rotating credentials. Version 0.2.0 adds central challenge registration, private challenge-answer ownership,
progress summaries, and administrator-managed challenge enrollment.
Version 0.3.0 adds a five-section RTL learner workspace (home, challenges,
recent activity, profile, account security) with self-service display-name
editing, secure code/link rotation and device/session termination. Account
settings use learner-session-bound nonces rather than globally shared
WordPress guest nonces. No theme update beyond 1.3.8 is required for Phase C.
Version 0.4.0 adds a real dynamic Gutenberg question block for existing
resource, podcast and lesson editors: reflective mode or per-learner private
draft/final answers, stable block/question IDs, server-side save/ownership
validation and a private personal-answers dashboard tab. No additional theme
upgrade is required for Phase D; upgrade the standalone plugin.
Version 0.5.0 (Phase E) adds private, filtered educational-response
reporting, audited answer detail views, a dedicated capability restricted to
explicitly authorized WordPress staff, and a per-question-block manager
visibility switch. Upgrade only the standalone plugin for Phase E; theme
v1.3.8 remains unchanged.
Version 0.6.0 (Phase F), together with theme 1.3.9, adds centralized
course enrollment, explicit lesson completion, real course percentages and
learner-dashboard course progress. Open enrollment is off by default on all
courses; administrators can approve/restore/revoke memberships. Membership
does not provide billing verification or lesson paywall protection. Update the
WordPress theme **and** the separate plugin together. The original challenge-only
codes and bearer links are intentionally retired when the plugin is active;
old challenge participant records are left untouched but not transferred.
A larger academy content/editor redesign remains a separate project;
Phase E implemented private answer reports and Phase F implemented course memberships.

Production login/registration requires HTTPS. No email and no login lockout
are used, per project requirements. Eight-digit codes with unlimited login
attempts have a code-guessing security risk.

See the [plugin README](./morvarid-learning-core/README.md) for installation,
security details, and a staging acceptance-test checklist.
