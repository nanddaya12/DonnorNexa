# AI usage declaration (required by the CEP brief)

Sir's rule: AI-generated code must be declared and fully understood by its author (checked in the viva).

**Tool(s) used:** Claude (Anthropic) - used to generate the first version of the PHP/SQL/JS code and documentation for this project.

| Date | Member | What AI produced | Files affected | Reviewed and understood by |
|---|---|---|---|---|
| | | Project scaffold, schema, seed generator | `sql/*`, `config/*` | |
| | | Core classes (router, auth, validator...) | `src/Core/*` | |
| | | Matching, request, expiry services | `src/Services/*` | |
| | | Controllers and templates | `src/Controllers/*`, `templates/*` | |
| | | CSS and JavaScript | `public/assets/*` | |

**What we changed or extended ourselves:** (write this honestly - UI tweaks, new features, bug fixes)

**Each member's explanation checklist (be ready to explain without notes):**
- [ ] The `SELECT ... FOR UPDATE` transaction in `RequestService::accept()` and why it prevents over-allocation
- [ ] The ranking score formula in `DefaultScoringStrategy`
- [ ] How CSRF tokens and sessions work here (`Csrf.php`, `Session.php`, `Auth.php`)
- [ ] The trigger `trg_donation_after_insert` and the view `v_eligible_donors`
- [ ] Why `e()` / prepared statements stop XSS / SQL injection
