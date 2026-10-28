# Deploying SkillSwap

SkillSwap ships as a Docker image (PHP 8.3 + Apache) that needs **one MySQL 8 / MariaDB database** and a handful of environment variables. On start-up it creates the tables itself and creates the first admin account from your settings. These steps use **Railway**, which provides MySQL next to your app, but any Docker host works the same way.

> Render does not offer managed MySQL (only PostgreSQL), so on Render you would also need an external MySQL provider. Railway avoids that.

## 1. Put the code on GitHub

From the project folder (one time):

```
git init
git add .
git commit -m "SkillSwap"
git branch -M main
git remote add origin https://github.com/<your-username>/skillswap.git
git push -u origin main
```

`.gitignore` already keeps uploads and any `.env` file out of the repository. **Never commit passwords.**

## 2. Create the project on Railway

1. <https://railway.app> → **New Project** → **Deploy from GitHub repo** → pick `skillswap`. Railway detects the `Dockerfile` and `railway.json` automatically.
2. In the same project click **+ New → Database → MySQL**.
3. Open your **app service → Variables** and add:

| Variable | Value | Notes |
|---|---|---|
| `DATABASE_URL` | `${{MySQL.MYSQL_URL}}` | Reference to the MySQL service (rename `MySQL` if your service has another name) |
| `ADMIN_EMAIL` | your email | Becomes the first admin |
| `ADMIN_PASSWORD` | 12+ characters | Used only to *create* the account; see step 5 |
| `ADMIN_NAME` | your name | Optional |

   `APP_ENV=production` and `TRUST_PROXY=1` are already set inside the image.
4. **Persistent photos:** app service → **Settings → Volumes → Add volume**, mount path `/data`. Without a volume, uploaded profile photos disappear on every redeploy (everything else lives in MySQL).
5. **Settings → Networking → Generate Domain.** You get an `https://…up.railway.app` address to share.

Railway builds the image, starts it, waits for `/health.php` to return 200, and switches traffic over. The first start creates all tables and the admin account (watch **Deployments → View logs**: `Database ready (7 tables).` and `Admin account created for …`).

## 3. First login

Open the domain, click **Log in**, sign in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`. You land on the **Admin** overview. Students register themselves through **Sign up**.

## 4. Roles

| Role | Can do |
|---|---|
| **Student** (default for everyone who signs up) | Manage own profile, skills and photo; browse/search; send and answer exchange requests; change own password |
| **Admin** | Everything a student can, plus: view all accounts, suspend / reactivate, promote / demote, delete accounts, remove any skill listing, see the audit log |

Rules enforced on the server: students get **403** on every admin URL; an admin cannot suspend, demote or delete themselves; another admin must be demoted before they can be suspended or deleted; every admin action is written to the audit log; a suspended student is logged out on their next request and hidden from the site.

To make more admins: sign in as admin → **Manage users → Make admin**.

## 5. After the first boot

- Sign in once and, if you like, **change the admin password** (Edit profile → Change your password).
- You can now **delete the `ADMIN_PASSWORD` variable**. It is only read when the admin account does not exist yet; an existing account's password is never overwritten.
- Do **not** set `SEED_DEMO=1` on a real site: it creates demo students whose password is public.

## 6. Settings reference

| Variable | Default | Purpose |
|---|---|---|
| `DATABASE_URL` / `MYSQL_URL` | | `mysql://user:pass@host:port/db`. Alternatives: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (or Railway's `MYSQLHOST`, …) |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` | | Creates / promotes the admin at start-up |
| `APP_ENV` | `production` in the image | `production` = secure cookies, HSTS, no error details |
| `APP_TZ` | `Asia/Kolkata` | Time zone used by PHP and MySQL sessions |
| `TRUST_PROXY` | `1` in the image | Read visitor IP / https from `X-Forwarded-*`. Leave on behind Railway/Render/nginx |
| `UPLOAD_DIR` | `/data/avatars` | Where profile photos are stored (mount your volume at `/data`) |
| `MAX_SKILLS_PER_USER` | `50` | Listing cap per student |
| `RATE_LOGIN_MAX`, `RATE_LOGIN_IP_MAX`, `RATE_REGISTER_MAX`, `RATE_REQUEST_MAX` | `5`, `60`, `30`, `20` | Abuse limits (per 15 min / 15 min / hour / hour) |
| `SEED_DEMO` | off | `1` loads demo students (known passwords, for demos only) |

## 7. Check it is healthy

- `https://<your-domain>/health.php` → `{"status":"ok"}`.
- Browser address bar shows a padlock; `Strict-Transport-Security` and `Content-Security-Policy` headers are sent.
- Register a test student, add a skill, log in as the admin and open **Manage users**.

## 8. Things to know (honest limitations)

- **No password reset by email.** There is no mail server configured. A student who forgets their password cannot recover it by themselves; an admin can delete the account so they can register again. Adding SMTP-based reset is the natural next feature.
- **No email verification.** Anyone can register with any address; admins can suspend abusers.
- **Sessions live on the container disk**, so a redeploy logs everyone out. Fine for one instance; use database sessions before scaling to several.
- **Rate limiting uses the visitor IP from `X-Forwarded-For`** (last entry). If you see everyone locked out together, your host adds extra proxy hops; raise `RATE_LOGIN_IP_MAX` / `RATE_REGISTER_MAX`.
- **Backups:** Railway can snapshot the database volume, and you can take a dump with `mysqldump` through the MySQL service's connection details. Back up before upgrades.
- **Updating:** push to `main`; Railway rebuilds. `database/install.php` upgrades the schema safely on start-up (it never drops anything).

## Try the production image on your own computer first

```
docker compose up --build
```

then open <http://localhost:8080> (admin: `admin@skillswap.test` / `local-admin-pass-123`, demo students: `aarav@skillswap.test` / `Password@123`). Browsers accept the `Secure` cookie on `localhost`; other hosts need https.
