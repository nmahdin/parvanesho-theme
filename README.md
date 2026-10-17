# parvanesho-theme

## Theme

The active WordPress theme is in `morvarid-daron-theme/`.

## Unified learner identity — Phase A

A new **separately installable WordPress plugin** is in
[`morvarid-learning-core/`](./morvarid-learning-core/README.md).

This plugin is **not automatically installed by updating the theme**.
To use Phase A, copy the `morvarid-learning-core` directory to
`wp-content/plugins/`, activate **Morvarid Learning Core**, and open:

- `/learning-register/` — create learner identity with name only.
- `/learning-login/` — sign in with global eight-digit access code.
- `/learning-dashboard/` — personal identity dashboard.

WordPress administrators get a separate **یادگیرندگان** menu for issuing and
rotating credentials. The Phase A dashboard shows placeholders for challenges,
resources and courses until their subsequent integration phases.

Production login/registration requires HTTPS. No email and no login lockout
are used, per project requirements. Eight-digit codes with unlimited login
attempts have a code-guessing security risk.

See the [plugin README](./morvarid-learning-core/README.md) for installation,
security details, and a staging acceptance-test checklist.
