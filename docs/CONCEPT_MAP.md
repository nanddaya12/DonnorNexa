# Concept -> file / class map (required in the report)

| Concept (brief, section 6) | Where it is implemented |
|---|---|
| Variables, types, arrays, functions, loops, conditionals | `src/helpers.php`; `src/Services/MatchingService.php` (`CompatibilityService`, `DefaultScoringStrategy`); `MatchingService::notifyBatch()` |
| OOP: classes, interfaces, inheritance, traits, namespaces, autoloading | Autoloader in `src/bootstrap.php`; interface `Contracts/ScoringStrategy` (in `MatchingService.php`); inheritance `BaseController`, `BaseRepository`, `Models/Model`; trait `Traits/Auditable` (in `Database.php`); namespace `DonorNexa\...` in every class |
| Forms and superglobals | `$_POST`, `$_GET`, `$_FILES`, `$_SERVER`, `$_SESSION`, `$_COOKIE` in `Controllers/*`, `Core/Session.php`, `Core/Auth.php`, `Repositories/BaseRepository.php` |
| Sessions and cookies | `Core/Session.php`, `Core/Auth.php` (login state, remember-me cookie), flash messages in `Session::flash()`; role guard in `Core/Router.php` |
| include/require and templating | `templates/layout.php`, `templates/partials/*`; front controller `public/index.php`; router `Core/Router.php` + `config/routes.php`; `BaseController::render()` |
| File handling and uploads | `Services/RegistrationService.php` (`UploadService`: photo, licence), `Services/ReportService::exportCsv()`, `Core/ErrorHandler.php` (`Logger`: error.log), `AdminController::licence()` |
| Date and time functions | `date_default_timezone_set('Asia/Karachi')` in `bootstrap.php`; MySQL `time_zone`; cool-down in `Models/Donor::eligibleOn()`; JS countdown in `assets/js/app.js`; `Services/RequestService.php` (`ExpiryService`) |
| Regex and filter_var | `Core/Validator.php` (CNIC, mobile, name, password; `filter_var` for email/int); mirrored in `assets/js/app.js` |
| Error and exception handling | `src/Exceptions/AppException.php`, `Core/ErrorHandler.php`, try/catch in controllers and `Core/Database.php`, error pages `templates/errors/*` |
| MySQL with PDO, prepared statements, transactions | `Core/Database.php`; all `Repositories/*`; `RequestService::accept()/markDonated()/create()` |
| Joins, aggregates, GROUP BY, subqueries, views | `sql/queries.sql` (8 queries), `v_eligible_donors` in `sql/schema.sql`, `ReportService::chartData()` |
| HTML5 semantic markup and ARIA | `templates/*` (header/nav/main/section/article/figure, labels, `aria-live`, `aria-sort`, `role="timer"`) |
| CSS3 Flexbox/Grid, media queries, variables | `public/assets/css/style.css` (100% hand-written, mobile-first) |
| JavaScript DOM, events, Fetch, validation | `public/assets/js/app.js` (countdowns, live stats, emergency board, debounced donor search, availability toggle, inline validation), `admin.js` (charts) |
| Security | `Core/Csrf.php`, `Core/Auth.php` (`password_hash`), `e()` in `helpers.php`, prepared statements, security headers in `public/index.php` |
| Pagination, sorting, search | `Core/Paginator.php`, list methods in repositories, `partials/pagination.php`, `sort_link()` helper |

## Database features (section 7)
(a) 3NF with PK/FK and ON DELETE rules - `sql/schema.sql`. (b) ENUM, CHECK, UNIQUE - same file. (c) indexes with comments explaining why - same file. (d) VIEW `v_eligible_donors`. (e) TRIGGERS `trg_donation_after_insert`, `trg_requests_critical_limit`. (f) TRANSACTIONS in `RequestService` and `RegistrationService`. (g) 8 JOIN/aggregate queries in `sql/queries.sql`.
