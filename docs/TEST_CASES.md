# Test-case table (all executed against the seeded database)

| # | Area | Test | Expected | Result |
|---|---|---|---|---|
| 1 | Registration | Submit donor form with invalid email and weak password | Redirect back, field errors, typed values kept | Pass |
| 2 | Registration | Register donor with PNG photo | Account created, photo re-encoded | Pass |
| 3 | Registration | Register again with same email | "already exists" | Pass |
| 4 | Upload | Upload PHP code renamed to .png | Rejected by MIME check | Pass |
| 5 | Registration | Register hospital with PDF licence | Account Pending | Pass |
| 6 | Auth | Wrong password | Generic error message | Pass |
| 7 | Auth | Donor opens admin page | 403 page | Pass |
| 8 | Auth | POST with wrong CSRF token | 403 | Pass |
| 9 | Auth | Remember-me: delete session cookie, reopen dashboard | Still logged in | Pass |
| 10 | Auth | Logout (POST) | Session and remember cookie cleared | Pass |
| 11 | Hospital | Pending hospital tries to raise request | Refused | Pass |
| 12 | Admin | Verify hospital, view licence PDF | Status Verified, audit row | Pass |
| 13 | Request | Units = 99; past deadline | Validation errors | Pass |
| 14 | Engine | Critical O+ request | 5 compatible, eligible donors notified (pool widened) | Pass |
| 15 | Engine | All matched donors checked against `blood_compatibility` and `v_eligible_donors` | 0 violations | Pass |
| 16 | Privacy | Hospital views request before acceptance | Phones masked | Pass |
| 17 | Security | `<script>` in ward notes | Escaped in HTML | Pass |
| 18 | Anti-misuse | Raise 4th open Critical request | Refused | Pass |
| 19 | Concurrency | 4 donors accept a 1-unit request simultaneously | Exactly 1 accepted, others get "already covered" | Pass |
| 20 | Privacy | Phone after acceptance | Revealed, `contact_revealed` audited once | Pass |
| 21 | Donation | Mark donated | Inventory +1, trigger updates last donation, request closed | Pass |
| 22 | Donation | Mark same donation twice | Only 1 donation row | Pass |
| 23 | Trust | Cancel request | Trust score -5 | Pass |
| 24 | Ownership | Other hospital opens the request | 403 | Pass |
| 25 | Expiry | Late open request + `cli/expire.php` | Expired, audited as system | Pass |
| 26 | Admin | Search/filter/sort/paginate users, requests, audit | Works; bad sort key ignored | Pass |
| 27 | Admin | CSV export (requests, audit); unknown type | CSV download; 404 | Pass |
| 28 | Admin | Settings: cool-down 5 (invalid) then 60 | Rejected / saved | Pass |
| 29 | Admin | Add and delete unused city | Works | Pass |
| 30 | Admin | Block then unblock a donor | Cannot log in / can log in | Pass |
| 31 | API | Availability toggle with and without CSRF header | OK / 403 | Pass |
| 32 | API | Donor calls hospital donor-search | 403 | Pass |
| 33 | API | Donor search response | No phone field | Pass |
| 34 | Public | Emergencies board | No phone or email | Pass |
| 35 | Errors | Unknown route | Friendly 404 page | Pass |
| 36 | Errors | Database down | Friendly 503 page and log entry | Pass |

Add your own screenshots next to these rows in the report. The performance requirement (every page under 2 s on localhost) should be re-measured on your machine.
