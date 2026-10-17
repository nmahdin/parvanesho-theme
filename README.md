# parvanesho-theme

## Theme

The active WordPress theme is in `morvarid-daron-theme/`.

## Unified learner identity — Phases A + B + C

A new **separately installable WordPress plugin** is in
[`morvarid-learning-core/`](./morvarid-learning-core/README.md).

This plugin is **not automatically installed by updating the theme**.
To use Phases A–C, copy the `morvarid-learning-core` directory to
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
WordPress guest nonces. No theme update beyond 1.3.8 is required for Phase C. Update the
WordPress theme **and** the separate plugin together. The original challenge-only
codes and bearer links are intentionally retired when the plugin is active;
old challenge participant records are left untouched but not transferred.
Educational files and course enrollments remain future phases.

Production login/registration requires HTTPS. No email and no login lockout
are used, per project requirements. Eight-digit codes with unlimited login
attempts have a code-guessing security risk.

See the [plugin README](./morvarid-learning-core/README.md) for installation,
security details, and a staging acceptance-test checklist.
