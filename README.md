# SkillSwap: Student Skill Exchange Platform

A web platform where students list the skills they **can teach** and the skills they **want to learn**, find peers, and organise skill exchanges.

Built with **HTML, CSS, JavaScript, PHP 8 and MySQL** as a BCA Summer Internship Project (MIT School of Business & Computer Applications, Pune).

**Deploying it for others to use?** See [docs/DEPLOY.md](docs/DEPLOY.md) (Docker image + Railway, step by step).

## Features

| Module | What it does |
|---|---|
| Registration & login | Sign up with name, email and password (bcrypt). Session login, database-backed lockout after repeated failures, logout, change password. |
| Student profiles | Course, year, college, bio, phone, profile link and photo. Contact details stay private until an exchange is accepted. |
| Skill listings | Add, edit and delete skills you **can teach** or **want to learn**, with category, level and meeting mode. |
| Search & filter | Keyword search; filters for type, category, level and mode; sorting and pagination. |
| Exchange requests | Request someone's skill, optionally offering one of yours. Received/Sent tabs; accept, decline, cancel, complete. |
| Dashboard | Stats, pending requests with one-click accept/decline, suggested teachers ("mutual match"), profile completeness. |
| **Roles (RBAC)** | **Student** and **Admin**. Admins manage accounts (suspend, reactivate, promote, demote, delete), remove listings, and see an audit log. |
| Extras | Responsive layout, light/dark theme, client-side validation, flash messages, health check endpoint. |

### Roles and access control
| | Guest | Student | Admin |
|---|---|---|---|
| Browse skills, view profiles | ✔ | ✔ | ✔ |
| Own profile, skills, requests | | ✔ | ✔ |
| `admin.php`, `admin_users.php`, `admin_skills.php` | redirect to login | **403** | ✔ |

The role is read from the database on every request, so promoting, demoting or suspending someone takes effect immediately.

### Security measures
- Prepared statements (PDO) for every query; bcrypt password hashes; session ID regenerated on login
- CSRF token on every state-changing form (logout and all admin actions are POST)
- All output escaped (`e()`), plus a strict **Content-Security-Policy** (scripts only from this site or with a per-request nonce), `X-Frame-Options`, `nosniff`, `Permissions-Policy`, HSTS over https
- Server-side validation everywhere; ownership checks on skills and request status changes; role checks on every admin page and action
- Database-backed rate limits (login per IP+email and per IP, sign-up, requests, password change) that cannot be bypassed by clearing cookies
- Timing-safe login (same work whether or not the email exists), generic error messages
- Uploads: validated by image content, random file names, served through `avatar.php` from a folder outside the web root in production
- Errors are logged, never shown to visitors; sensitive folders and files (`includes/`, `database/`, Dockerfile, …) are not downloadable

## Project structure

```
SkillSwap/
├── index.php, register.php, login.php, logout.php, change_password.php
├── dashboard.php, profile.php, profile_edit.php
├── skills.php, skill_form.php, skill_delete.php
├── request_create.php, requests.php
├── admin.php, admin_users.php, admin_skills.php      Admin area (role: admin)
├── avatar.php, health.php, 404.php, router.php (dev server only)
├── css/style.css, js/script.js, assets/logo.svg
├── includes/            config (env vars), db, auth + RBAC, csrf, ratelimit, helpers, partials, header, footer
├── database/
│   ├── schema.sql       Idempotent schema (source of truth)
│   ├── skillswap.sql    XAMPP import (drops and recreates; generated from schema.sql)
│   ├── install.php      Creates/upgrades tables (runs automatically in Docker)
│   ├── create_admin.php Creates or promotes an admin
│   └── seed.php         Demo data
├── Dockerfile, docker/, docker-compose.yml, railway.json, .dockerignore
└── docs/DEPLOY.md, docs/TEST_CASES.md
```

## Database

| Table | Purpose |
|---|---|
| `users` | Account: name, unique email, password hash, **role** (student/admin), **status** (active/suspended) |
| `profiles` | One-to-one student details |
| `categories` | Fixed list of skill categories |
| `skills` | A skill a user can teach or wants to learn |
| `exchange_requests` | Sender → receiver request for a skill, optional offered skill, status |
| `admin_audit` | Record of every admin action |
| `rate_limits` | Counters behind the abuse limits (identifiers are hashed) |

Request status flow: `pending → accepted | rejected | cancelled`, then `accepted → completed`.

## Run locally

### Option A: XAMPP (Windows), as in the report
1. Install **XAMPP** (PHP 8.1+) and start **MySQL**.
2. Create the database: `C:\xampp\mysql\bin\mysql.exe -u root < database\skillswap.sql`
   (already have an older database? run `C:\xampp\php\php.exe database\install.php` instead; it upgrades in place and keeps your data)
3. *(Optional)* demo data: `C:\xampp\php\php.exe database\seed.php` (password `Password@123`, e.g. `aarav@skillswap.test`)
4. *(Optional)* admin account: `C:\xampp\php\php.exe database\create_admin.php you@example.com "Your Name"`
5. Run: `C:\xampp\php\php.exe -S localhost:8000 router.php` → <http://localhost:8000>
   (or copy the folder to `C:\xampp\htdocs\SkillSwap` and use Apache)

Settings come from environment variables; the defaults match XAMPP (`root`, no password).

### Option B: Docker (same image used in production)
`docker compose up --build` → <http://localhost:8080> (includes MySQL 8, demo data and an admin; see the compose file for credentials)

## Testing

[docs/TEST_CASES.md](docs/TEST_CASES.md) lists 84 checks with their recorded results: registration, login, profiles, skills, database, search, exchange requests, security, role-based access control, production hardening and responsive UI.
