# Deploying SkillSwap on Render (free)

SkillSwap ships as a Docker image (PHP 8.3 + Apache) that needs **one MySQL database** and a few environment variables. It keeps **nothing on the server's disk**: sessions, profile photos and everything else live in MySQL, so it works on Render's free tier, which wipes the filesystem whenever the service sleeps.

Render has no managed MySQL, so you create a free MySQL on **Aiven** and point the app at it.

| Piece | Service | Cost |
|---|---|---|
| Web app (Docker) | Render, free web service | Free, no card required according to Render's docs |
| Database | Aiven, free MySQL plan (1 GB) | Free, no card, no time limit according to Aiven's docs |

> Free-tier terms change. Re-check both sign-up pages if something differs from this guide.

## What to expect on the free tier
- Render puts a free service to **sleep after 15 minutes without traffic**; the next visitor waits about a minute while it wakes up. Because everything is stored in MySQL, nobody loses their login or photo.
- Render gives 750 free instance-hours a month, enough for one service to run all month.
- Aiven may power off a free database that sits unused and notifies you first. Switch it back on from the Aiven console.

## 1. Create the free MySQL on Aiven
1. Sign up at <https://aiven.io> and create a new service: **MySQL → Free plan**, choose a region (pick one near your Render region, Singapore by default), name it `skillswap-db`.
2. Wait until the service shows **Running**.
3. On the service **Overview**, copy the **Service URI**. It looks like:
   `mysql://avnadmin:PASSWORD@host.aivencloud.com:12345/defaultdb?ssl-mode=REQUIRED`
   Keep it secret: it is your database password.

## 2. Create the web service on Render
1. Sign up at <https://render.com> (GitHub login is easiest) and allow access to the `skill_swap` repository.
2. **New → Blueprint** → choose the repository. Render reads `render.yaml` and asks for three values:

| Value | What to enter |
|---|---|
| `DATABASE_URL` | The Aiven Service URI from step 1 |
| `ADMIN_EMAIL` | Your email; it becomes the first admin |
| `ADMIN_PASSWORD` | 12+ characters; used only to create the admin |

3. Click **Apply**. Render builds the Docker image (3–6 minutes) and starts it. In the service **Logs** you should see `Database ready (8 tables).` and `Admin account created for …`.
4. Your site is live at `https://skillswap-xxxx.onrender.com` (shown at the top of the service page).

(Manual alternative to the Blueprint: **New → Web Service** → the repo → **Language: Docker** → **Free** instance → add the variables above plus `DB_SSL=1` → **Health Check Path** `/health.php`.)

## 3. First login
Open the site, **Log in** with `ADMIN_EMAIL` / `ADMIN_PASSWORD`, and you land on the **Admin** overview. Students register themselves through **Sign up**.

## 4. Roles

| Role | Can do |
|---|---|
| **Student** (default for everyone who signs up) | Manage own profile, skills and photo; browse/search; send and answer exchange requests; change own password |
| **Admin** | Everything a student can, plus: view all accounts, suspend / reactivate, promote / demote, delete accounts, remove any skill listing, see the audit log |

Enforced on the server: students get **403** on every admin URL; an admin cannot suspend, demote or delete themselves; another admin must be demoted before being suspended or deleted; every admin action is written to the audit log; a suspended student is logged out on their next request and hidden from the site. To add more admins: **Manage users → Make admin**.

## 5. After the first boot
- Optionally change the admin password (Edit profile → Change your password).
- You may **delete the `ADMIN_PASSWORD` variable** in Render. It is only read when the admin account does not exist yet and never overwrites an existing password.
- Do **not** set `SEED_DEMO=1` on a real site: it creates demo students with a public password.

## 6. Keeping it awake (optional)
Sleeping is harmless, but the first visit after a quiet period is slow, which is awkward for a live demo. A free uptime monitor (for example UptimeRobot) that requests `https://<your-site>/health.php` every 5–10 minutes keeps it awake. This uses roughly all of the 750 monthly free hours for the one service. Or simply open the site a minute before presenting.

## 7. Settings reference

| Variable | Default | Purpose |
|---|---|---|
| `DATABASE_URL` / `MYSQL_URL` | | `mysql://user:pass@host:port/db`. Alternatives: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` |
| `DB_SSL` | `0` | `1` = encrypted database connection (needed by hosted MySQL such as Aiven; also switched on by `?ssl-mode=REQUIRED` in the URL) |
| `DB_SSL_CA_PEM` | | Optional: the provider's CA certificate text (replace line breaks with `\n`). With it the server's identity is verified; without it the connection is encrypted but not verified |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` | | Creates / promotes the admin at start-up |
| `APP_ENV` | `production` in the image | Secure cookies, HSTS, no error details |
| `APP_TZ` | `Asia/Kolkata` | Time zone for PHP and MySQL sessions |
| `TRUST_PROXY` | `1` in the image | Read visitor IP / https from `X-Forwarded-*` (leave on behind Render) |
| `MAX_SKILLS_PER_USER` | `50` | Listing cap per student |
| `RATE_LOGIN_MAX`, `RATE_LOGIN_IP_MAX`, `RATE_REGISTER_MAX`, `RATE_REQUEST_MAX` | `5`, `60`, `30`, `20` | Abuse limits (per 15 min / 15 min / hour / hour) |
| `SEED_DEMO` | off | `1` loads demo students (known passwords, demos only) |

## 8. Check it is healthy
- `https://<your-site>/health.php` → `{"status":"ok"}` (the first request after sleep may take a minute).
- The browser shows a padlock. Register a test student, add a skill and a profile photo, then log in as the admin and open **Manage users**.

## 9. Things to know (honest limitations)
- **No password reset by email.** No mail server is configured, so a student who forgets their password cannot recover it alone; an admin can delete the account so they can register again. SMTP-based reset is the natural next feature.
- **No email verification.** Anyone can register with any address; admins can suspend abusers.
- **Profile photos live in the database** (the browser shrinks them to 512 px first, 1 MB server cap), which keeps the app stateless but uses the free 1 GB sparingly.
- **Without `DB_SSL_CA_PEM` the database link is encrypted but the server certificate is not verified.** Add the CA certificate from the Aiven console for full verification.
- **Rate limiting uses the visitor IP from `X-Forwarded-For`** (last entry). If everyone appears to share one IP behind the host's proxies, raise `RATE_LOGIN_IP_MAX` / `RATE_REGISTER_MAX`.
- **Backups:** Aiven keeps automatic backups on its plans; check what the free plan retains. Export with `mysqldump` before big changes.
- **Updating:** push to `main`; Render rebuilds. `database/install.php` upgrades the schema on start-up and never drops anything.

## Other hosts
The same image runs anywhere that runs Docker and provides a MySQL 8 / MariaDB 10.4+ database: Railway (`railway.json` is included; MySQL is built in there), Fly.io, a VPS, and so on. Set the same variables.

## Try the production image on your own computer first

```
docker compose up --build
```

Then open <http://localhost:8080> (admin: `admin@skillswap.test` / `local-admin-pass-123`, demo students: `aarav@skillswap.test` / `Password@123`). Browsers accept the `Secure` cookie on `localhost`; other hosts need https.
