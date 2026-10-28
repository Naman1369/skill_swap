# SkillSwap: Test Cases

The IDs REG, LOG, PRO, SKL and DB match Chapter 6.5 of the project report. SRCH and REQ cover the search and exchange-request modules. SEC lists the security checks from §6.8.

**How to use:** run each case in the local environment (XAMPP, demo data loaded with `database/seed.php`). Record what actually happened in *Actual result* and mark *Status* as Pass or Fail. Unless stated otherwise, the demo login is `aarav@skillswap.test` / `Password@123`.

Environment used: Windows 11 · Microsoft Edge (headless, Chromium) · functional cases: PHP 8.2.12 + MariaDB 10.4.32 (XAMPP); production cases: the Docker image (php:8.3-apache) with MySQL 8.4 via `docker compose` · 28 Oct 2026

Execution: the functional, request, security and RBAC cases were run as an automated HTTP test script, with database rows checked through the MySQL client. The original 53 cases were run on XAMPP and again against the production Docker stack (all passed both times); RBAC and hardening cases were run on the Docker stack. UI cases were run in a real browser through the DevTools protocol.

## 6.5.1 Registration

| ID | Scenario | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| REG-01 | Open the registration page | Visit `register.php` | Form with name, email, password, confirm password loads | HTTP 200; form with name, email, password and confirm fields shown | ✅ Pass |
| REG-02 | Valid registration | Fill all fields with valid data and submit | Account created, user logged in and redirected to Edit profile with a welcome message | Redirected to profile_edit.php; "Welcome to SkillSwap, Test!" shown | ✅ Pass |
| REG-03 | Required fields empty | Submit an empty form | Inline "is required" errors; no account created | Errors shown: "Please enter your full name.", "Please enter your email address.", "Please choose a password." | ✅ Pass |
| REG-04 | Badly formatted email | Enter `abc@` as email | "Please enter a valid email address." | "Please enter a valid email address." shown | ✅ Pass |
| REG-05 | Duplicate email | Register with `aarav@skillswap.test` | "An account with this email already exists." | "An account with this email already exists." shown | ✅ Pass |
| REG-06 | Record stored | After REG-02 check `users` and `profiles` in phpMyAdmin | New row in both tables; `password_hash` starts with `$2y$` (not plaintext) | users row id 18 with matching profiles row; password_hash starts with $2y$ (bcrypt) | ✅ Pass |

## 6.5.2 Login

| ID | Scenario | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| LOG-01 | Open the login page | Visit `login.php` | Login form loads | HTTP 200; login form shown | ✅ Pass |
| LOG-02 | Valid credentials | Log in as Aarav | Redirected to the dashboard with "Welcome back, Aarav!" | Redirected to dashboard.php; "Welcome back, Aarav!" shown | ✅ Pass |
| LOG-03 | Wrong password | Correct email, wrong password | "Invalid email or password." | "Invalid email or password." shown | ✅ Pass |
| LOG-04 | Unregistered email | `nobody@skillswap.test` | Same generic "Invalid email or password." | Same generic "Invalid email or password." shown | ✅ Pass |
| LOG-05 | Empty fields | Submit an empty form | Inline required-field errors | Both required-field errors shown | ✅ Pass |
| LOG-06 | Access after login | Log in, then open Dashboard, Requests and My profile | Pages open; logged out, they redirect to login and return to the requested page after login | Logged out: requests.php redirected to login; after login returned to /requests.php?tab=outgoing; dashboard/requests/profile all HTTP 200 | ✅ Pass |
| LOG-07 | Logout | Click **Log out** in the navbar | Redirected home; private pages require login again | POST logout redirects home; dashboard then requires login | ✅ Pass |

## 6.5.3 Student profile

| ID | Scenario | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| PRO-01 | Open profile page | Click **My profile** | Profile with "Can teach" and "Wants to learn" sections | Own profile with "Can teach" and "Wants to learn" sections | ✅ Pass |
| PRO-02 | View stored information | Open `profile.php?id=` of another student | Their name, course, college, bio and skills are shown; contact hidden unless an exchange was accepted | Kabir's name, course, bio and skills shown; contact hidden (no accepted exchange) | ✅ Pass |
| PRO-03 | Save valid profile | Edit profile: change course, bio, phone; upload a JPG | Photo saved with a random name and shown through `avatar.php` | "Your profile has been updated."; new bio shown; photo saved as e359114205e469723ab4fe2aa220e2a5.png and served through avatar.php | ✅ Pass |
| PRO-04 | Invalid profile data | Clear the name; enter phone `abc`; link `linkedin.com` (no https) | Field errors shown, nothing saved | Errors for name, phone and link shown; nothing saved | ✅ Pass |
| PRO-05 | Retrieve after saving | Reload the profile and the dashboard | Saved data appears; completeness bar updated | Saved data shown on profile; dashboard shows 86% complete (only "List at least one skill" missing) | ✅ Pass |

## 6.5.4 Skill listings

| ID | Scenario | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| SKL-01 | Open add-skill page | Click **Add a skill** | Form with teach/learn choice, name, category, level, mode, description | Form with type, name, category, level, mode and description | ✅ Pass |
| SKL-02 | Submit valid skill | Add "Excel shortcuts", Data & AI, teach | Success message; skill appears under "Can teach" | "Skill added!" message; skill listed under Can teach | ✅ Pass |
| SKL-03 | View saved skill | Open Browse skills and search "Excel shortcuts" | Card shows correct title, category, level, mode, owner | Card shows title, Data & AI, Intermediate, Online and owner Test Student | ✅ Pass |
| SKL-04 | Missing required fields | Submit without name or category | "Please enter the skill name." / "Please choose a category." | "Please enter the skill name." and "Please choose a category." shown | ✅ Pass |
| SKL-05 | Record in database | Check `skills` table | Row with correct `user_id`, `category_id`, `type` | Row present: user_id matches, category_id=4, type=teach | ✅ Pass |
| SKL-06 | Edit and delete own skill | Edit a listing, save, then delete it | Changes saved; deleted row removed | Edited title saved, then deleted; row removed | ✅ Pass |

## 6.5.5 Database connectivity

| ID | Scenario | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| DB-01 | App with MySQL running | Open the home page | Live counts and recent skills load | Home page loads with live counts (9 students) and 6 recent skills | ✅ Pass |
| DB-01b | App with MySQL stopped | Stop MySQL, reload | Friendly "We can't reach the database" page, with no credentials shown | MySQL shut down, then skills.php requested: HTTP 503 with "We can't reach the database right now"; no credentials or SQL error text in the page | ✅ Pass |
| DB-02 | Insert on registration | Covered by REG-02 / REG-06 | Rows inserted | Covered by REG-02 / REG-06 | ✅ Pass |
| DB-03 | Authenticate existing user | Covered by LOG-02 | Account retrieved and password verified | Covered by LOG-02 | ✅ Pass |
| DB-04 | Retrieve profile | Covered by PRO-02 | Correct row returned | Covered by PRO-02 | ✅ Pass |
| DB-05 | Save / retrieve skill | Covered by SKL-02 / SKL-03 | Correct rows written and read | Covered by SKL-02 / SKL-03 / SKL-05 | ✅ Pass |

## Skill search and filtering (new)

| ID | Scenario | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| SRCH-01 | Keyword search | Search `python` | Only Python-related listings | 3 results, all Python: Python for Non-programmers; Python Basics; Python Programming for Beginners | ✅ Pass |
| SRCH-02 | Single filter | Category = Design | Only Design listings | 5 results, all in Design | ✅ Pass |
| SRCH-03 | Combined filters | Type = Can teach, Category = Data & AI, Level = Advanced | Only matching listings (e.g. Excel & Power BI Dashboards) | Exactly 1 result: Excel & Power BI Dashboards | ✅ Pass |
| SRCH-04 | No results | Search `zzzz` | "No matching skills" empty state with a Clear filters button | "No matching skills" empty state with Clear filters | ✅ Pass |
| SRCH-05 | Pagination | With no filters (34 demo skills, 12 per page) | 3 pages; Next/Previous work; filters kept across pages | 34 skills: page 1 shows 12, page 3 shows 10; page links keep filters (?type=learn&page=2) | ✅ Pass |

## Exchange requests (new)

| ID | Scenario | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| REQ-01 | Send request | As Aarav, open Rohan's "Java & Data Structures", click **Request swap**, offer "Python Programming", write a message | "Request sent…"; appears under Requests → Sent as Pending | "Request sent to Rohan." and request listed under Sent as Pending | ✅ Pass |
| REQ-02 | Duplicate blocked | Request the same skill again | Redirected with "You already have a pending request…" | Redirected with "You already have a pending request for …" | ✅ Pass |
| REQ-03 | Own skill blocked | Open `request_create.php?skill_id=<your own skill id>` | "You cannot send an exchange request for your own skill." | "You cannot send an exchange request for your own skill." | ✅ Pass |
| REQ-04 | Accept | Log in as Rohan → Requests → Received → **Accept** | Status Accepted; contact details shown to both students | Status Accepted; each side now sees the other's email | ✅ Pass |
| REQ-05 | Decline | As Aarav, decline Kabir's pending request | Status Declined | "You declined Kabir's request."; status Declined | ✅ Pass |
| REQ-06 | Cancel | As Aarav, cancel the pending request sent to Priya | Status Cancelled | "Your request has been cancelled."; status Cancelled | ✅ Pass |
| REQ-07 | Complete | As Aarav, mark the accepted exchange with Priya as completed | Status Completed; dashboard "Completed swaps" count increases | Status Completed; dashboard "Completed swaps" went from 0 to 1 | ✅ Pass |
| REQ-08 | Contact privacy | As Aarav, open Kabir's profile (no accepted exchange) and then Priya's | Kabir: "Contact details are shared once…"; Priya: email and phone shown | Kabir: contact locked; Priya (completed exchange): email and phone shown | ✅ Pass |
| REQ-09 | Navbar badge | As Aarav with pending received requests | Red count badge on **Requests** | Red badge "1" on Requests (Kabir's pending request) | ✅ Pass |
| REQ-10 | Invalid status change | As the receiver, try to "cancel" a request; as an unrelated student, try to complete it | Refused with an error / 404; status unchanged | Invalid transition refused with an error; an unrelated student gets 404; status unchanged | ✅ Pass |

## Security checks (§6.8)

| ID | Check | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| SEC-01 | SQL injection: search | Search `' OR 1=1 -- ` | Treated as plain text; no error, no extra results | HTTP 200, treated as text, "No matching skills" | ✅ Pass |
| SEC-02 | SQL injection: login | Email `' OR '1'='1`, any password | "Invalid email or password." | "Invalid email or password." | ✅ Pass |
| SEC-03 | XSS | Put `<script>alert(1)</script>` in bio and in a skill title | Shown as text; no alert box | Shown as escaped text (&lt;script&gt;) on profile and browse pages; no script tag in HTML | ✅ Pass |
| SEC-04 | CSRF | Remove the `csrf_token` field with DevTools and submit any form | 400 page: "Your session has expired…" | HTTP 400 "Your session has expired…" for missing and forged tokens; nothing saved, still logged in | ✅ Pass |
| SEC-05 | Edit someone else's skill | As Aarav, open `skill_form.php?id=<Priya's skill id>` | 403 "You can only edit your own skill listings." | Edit: HTTP 403; delete: HTTP 403; Priya's skill untouched | ✅ Pass |
| SEC-06 | Logged-out access | Log out, open `dashboard.php` | Redirected to login | All private pages redirect to login; GET logout.php does not log you out (POST only) | ✅ Pass |
| SEC-07 | Login throttling | Enter a wrong password 5 times | Locked for 15 minutes per IP + email; the lock lives in the database, so clearing cookies does not reset it | After 5 failures even the correct password is refused: "Too many failed attempts. Please wait 60 seconds…" | ✅ Pass |
| SEC-08 | Malicious upload | Rename a `.txt` file to `.jpg` and upload it as a profile photo | "Photo must be a JPG, PNG or WebP image." | "Photo must be a JPG, PNG or WebP image." | ✅ Pass |
| SEC-09 | Internal files hidden | Open `includes/config.php`, `database/skillswap.sql`, `README.md`, `nope.php` | 404 page for all (see SEC-14 for the extended list) | Internal files (includes/, database/, docs/, .md) and unknown pages return the 404 page | ✅ Pass |

## Role-based access control (RBAC): student and admin

| ID | Scenario | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| RBAC-01 | Guests cannot reach the admin area | Logged out, open `admin.php`, `admin_users.php`, `admin_skills.php` | Redirected to the login page | Guests are redirected to login.php for admin.php, admin_users.php and admin_skills.php | ✅ Pass |
| RBAC-02 | Students cannot reach the admin area | Log in as a student, open the three admin pages | HTTP 403 "You do not have permission…" | A logged-in student gets HTTP 403 "You do not have permission to open this page." on all three admin pages | ✅ Pass |
| RBAC-03 | Students cannot perform admin actions | As a student, POST "promote me" and "delete listing" with a valid CSRF token | 403; nothing changes | A student POSTing admin actions (promote self, delete a listing) gets 403; role and data unchanged | ✅ Pass |
| RBAC-04 | Admin sees admin pages; link shown only to admins | Log in as admin, open the pages; compare the navbar with a student's | 200 on all pages; "Admin" link only for admins | Admin gets 200 on all admin pages and sees the "Admin" nav link; students do not see the link | ✅ Pass |
| RBAC-17 | Admin login destination | Log in as the admin | Lands on the admin overview | Admin login lands on admin.php | ✅ Pass |
| RBAC-05 | Suspension takes effect immediately | Student is logged in; admin suspends them; student clicks anything | Next request logs the student out; audit row written | Vikram had an active session; after the admin suspended him his very next request was redirected to login; 1 audit row written | ✅ Pass |
| RBAC-06 | Suspended student cannot log in | Log in with the correct password, then a wrong one | Correct: "account has been suspended"; wrong: generic error | Correct password: "This account has been suspended…"; wrong password still gives the generic message | ✅ Pass |
| RBAC-07 | Suspended student is hidden | Browse, open profile as student / as admin, request their skill, check home counts | Hidden from students (404), visible to admin with a badge; counts exclude them | Suspended: skills hidden from browse; profile 404 for students but visible (with Suspended badge) to admin; cannot be requested; home page count excludes them | ✅ Pass |
| RBAC-08 | Reactivation | Admin reactivates the student | Can log in again; skills visible again | Reactivated: Vikram can log in again and his skills are back in browse | ✅ Pass |
| RBAC-09 | Promote / demote | Promote a student, open `admin.php` with their existing session, then demote | 200 straight after promotion, 403 straight after demotion | Promoted Isha: her existing session can open admin.php at once; after demotion she gets 403 again (role is read from the database on every request) | ✅ Pass |
| RBAC-10 | Admin cannot modify own account | Admin tries to suspend, demote and delete themselves | All refused; account unchanged | Suspend, demote and delete on your own account are all refused; admin unchanged | ✅ Pass |
| RBAC-11 | Another admin must be demoted first | Admin tries to suspend / delete another admin | Refused: "Remove the admin role … first" | Another admin cannot be suspended or deleted directly: "Remove the admin role … first" (demote, then act) | ✅ Pass |
| RBAC-12 | Delete user | Admin deletes a student who has a skill and a photo | Account, profile, skills and photo file are gone | Deleting a user removes the account, profile, skills (cascade) and the photo file | ✅ Pass |
| RBAC-13 | Remove a listing | Admin removes a skill listing | Row deleted; audit entry names the skill and owner | Admin removed a listing; audit detail: "Mobile Photography" by Kabir Mehta | ✅ Pass |
| RBAC-14 | Admin forms are CSRF protected | POST admin actions without a token | HTTP 400; nothing changes | Admin POSTs without a CSRF token are rejected (HTTP 400); nothing changed | ✅ Pass |
| RBAC-15 | Audit trail | Open the admin overview after the actions above | Every action listed with the admin's name | Admin overview shows the audit trail: suspend, reactivate, promote, demote, delete user, delete skill, with the admin name | ✅ Pass |
| RBAC-16 | User search and filters | Search by name, filter role/status, try `%_'` | Correct rows; special characters are safe | Admin user search + role/status filters work; wildcard / quote characters are handled safely | ✅ Pass |

## Production hardening

| ID | Scenario | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| AVATAR-01 | Profile photo delivery | Fetch a photo; try traversal, wrong extension, unknown names, direct `/uploads/` path | Served as an image with nosniff + long cache; everything else 404 | Photo served by avatar.php as image/png with nosniff + long cache; path-traversal / wrong extension / unknown names all 404; direct /uploads/ access blocked | ✅ Pass |
| SEC-11 | Security headers and cookie | Load the home page as a new visitor and inspect response headers/HTML | CSP with nonce, X-Frame-Options, nosniff, no version leaks, cookie HttpOnly + Secure + SameSite | CSP with per-request nonce matching the inline script (no un-nonced scripts), frame-ancestors none, X-Frame-Options, nosniff, no X-Powered-By, Server without version, cookie HttpOnly+Secure+SameSite | ✅ Pass |
| SEC-12 | Login lockout cannot be bypassed | 5 wrong passwords, each from a fresh session without cookies, then the right password | Still locked; other accounts unaffected | Lockout survives clearing cookies (stored in the DB per IP+email): after 5 failures even the right password is refused; a different account is unaffected | ✅ Pass |
| SEC-13 | Change password | Wrong current / weak / mismatched / valid change; then log in with old and new | Only the valid change works; old password stops working | Wrong current / weak / mismatched passwords rejected; valid change succeeds; old password stops working, new one works | ✅ Pass |
| SEC-14 | Sensitive files are not downloadable | Request source, SQL, config, Dockerfile, `.git`, upload-folder paths | All blocked (404 / 403) | All 15 internal / sensitive / unknown paths are blocked (404 page; only the non-existent .git path answers 403) | ✅ Pass |
| SEC-15 | Health check | Open `/health.php` | 200 `{"status":"ok"}`, not cacheable | health.php: HTTP 200 {"status":"ok"}, not cacheable, no internals | ✅ Pass |
| SEC-16 | Abuse limits | Add a 51st skill; send a 21st request within an hour | Both refused with a clear message | Per-user caps: 51st skill refused ("limit of 50 skills"); 21st exchange request in an hour refused | ✅ Pass |
| SEC-17 | Admin search is XSS-safe | Search for `<script>alert(1)</script>` in the admin area | Shown as text | Search text reflected in the admin page is escaped | ✅ Pass |

## Responsive / UI checks

| ID | Check | Expected result | Actual result | Status |
|---|---|---|---|---|
| UI-01 | 375 px width (DevTools mobile) | Hamburger menu works; no horizontal scrolling; cards stack | Emulated 375 px: 0 px horizontal overflow on home, browse, register, dashboard, requests, profile, edit profile, skill form, request form and 404 (dashboard overflow found and fixed); hamburger opens the menu, aria-expanded="true" | ✅ Pass |
| UI-02 | Dark mode toggle | Theme switches and is remembered after reload | Toggle switched dark → light; data-theme="light" kept after reload (localStorage) | ✅ Pass |
| UI-03 | Keyboard navigation | Tab through the navbar and forms; focus ring visible | Focused inputs show a 3 px indigo focus ring and primary border; skip link and focus-visible styles present | ✅ Pass |

| UI-04 | No CSP violations (production build) | Browse guest pages and every admin page in a real browser with the console open | No console errors or CSP violations; theme, menu, validation, confirm dialogs all still work | 0 console errors / CSP violations on 9 pages; inline theme script applied via nonce; confirm dialog intercepted a destructive click | ✅ Pass |
| UI-05 | Admin pages on a phone (375 px) | Open admin overview, users, skills, change password | No horizontal scrolling of the page (tables scroll inside their card) | 0 px page overflow on all four | ✅ Pass |

**Summary:** 84 / 84 passed.
