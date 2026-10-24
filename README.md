# Executive Desk

Executive Desk is an executive-assistant platform for managing an executive's calendar, tasks, presentations, briefings, and reminders from one place.

- **Stack:** Laravel 13 (PHP 8.4), React 19 + TypeScript, Inertia.js v3, Tailwind CSS 4, MySQL 8+, Laravel Fortify, Spatie Laravel Permission, Laravel queues and scheduler.
- **Roles:** System Administrator, Executive Assistant, Executive. Accounts are created by administrators; self-registration is disabled.
- **Time:** Every timestamp is stored in UTC. Each event, task, and presentation also keeps its IANA time zone (for example `Asia/Manila`). Each user sees times in their own configured zone, never the browser's.

---

## Contents

1. [Features](#features)
2. [Windows installation (PowerShell)](#windows-installation-powershell)
3. [Running the application locally](#running-the-application-locally)
4. [Configuring email, SMS, and push](#configuring-email-sms-and-push)
5. [Testing](#testing)
6. [How reminders work](#how-reminders-work)
7. [Production deployment](#production-deployment)
8. [Troubleshooting](#troubleshooting)
9. [Project status](#project-status)

---

## Features

| Area          | What it does                                                                                                                                                                                                                                                                                                                                                  |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Dashboard     | Shows today's meetings, the next meeting with a live countdown, the next 7 days and their meeting load, and pending, in-progress, overdue, and high-priority tasks. It also shows the day's completion, presentation deadlines, recent schedule changes, your upcoming reminders, delivery health, and briefing previews. All figures come from the database. |
| Calendar      | Day, week, month, and agenda views, plus search and filters. Appointments have attendees, agenda, notes, preparation checklist, documents, and history. You can reschedule or cancel them. Overlaps trigger a conflict warning and are saved only when someone explicitly chooses "Save anyway". Edits are guarded by optimistic locking.                     |
| Tasks         | Priorities, statuses, start and due dates, estimates, related meetings, checklists, attachments, and activity history. Tasks can be reassigned, shown as a list or a board, and repeat on a schedule; each occurrence is created exactly once and keeps its local due time across DST changes.                                                                |
| Presentations | Presentation date, venue or link, presenter, preparation and review deadlines, versioned private files, a checklist, and approval.                                                                                                                                                                                                                            |
| Reminders     | Default meeting reminders at 60, 30, 15, and 5 minutes. Every user can configure their own intervals, an appointment can override them, and individual reminders can be turned off.                                                                                                                                                                           |
| Notifications | An in-app bell with an unread count, plus email via SMTP, SMS through a provider-independent gateway, and Web Push (VAPID). A delivery history records every attempt; failed reminders can be retried.                                                                                                                                                        |
| Briefings     | Daily and weekly briefings are built from live data and sent at each user's chosen local time. They can be previewed in the app and downloaded as PDF.                                                                                                                                                                                                        |
| Reports       | Schedule, task completion, overdue tasks, meeting frequency, presentations, notification delivery, and audit activity. Exports are CSV, Excel, or PDF and follow the same access rules as the app.                                                                                                                                                            |
| PWA / offline | The app can be installed. When offline it shows a read-only snapshot of the next 7 days and your open tasks. Task status changes made offline sync on reconnection, with duplicate and conflict detection.                                                                                                                                                    |
| Security      | Policies for every action, an audit trail, rate limits, private file storage, encrypted push keys, security headers, and immediate sign-out of deactivated users.                                                                                                                                                                                             |

---

## Windows installation (PowerShell)

Run every command from the project folder (for example `C:\projects\executive-desk`) in **PowerShell**.

### 1. Install PHP 8.4

1. Download the **VS17 x64 Thread Safe** zip of PHP 8.4 from <https://windows.php.net/download/> and extract it to `C:\Program Files\php`.
2. Add it to your `PATH`:

    ```powershell
    [Environment]::SetEnvironmentVariable('Path', $env:Path + ';C:\Program Files\php', 'User')
    ```

    Open a new PowerShell window after changing `PATH`.

3. Create `php.ini` from the production template:

    ```powershell
    Copy-Item 'C:\Program Files\php\php.ini-production' 'C:\Program Files\php\php.ini'
    notepad 'C:\Program Files\php\php.ini'
    ```

4. In `php.ini`, set `extension_dir = "ext"` and enable (remove the leading `;` from) these extensions:
   `curl`, `fileinfo`, `gd`, `gmp`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `sodium`, `zip`.
   Also set `upload_max_filesize = 25M` and `post_max_size = 30M` so 20 MB document uploads work.
5. Check:

    ```powershell
    php -v
    php -m | Select-String -Pattern 'pdo_mysql|intl|gmp|gd|zip|mbstring'
    ```

### 2. Install Composer

Download and run `Composer-Setup.exe` from <https://getcomposer.org/download/>, then check:

```powershell
composer --version
```

### 3. Install Node.js and npm

Install the current **LTS** release from <https://nodejs.org/>, then check:

```powershell
node -v
npm -v
```

### 4. Install and start MySQL

Install **MySQL Community Server 8.0 or later** (<https://dev.mysql.com/downloads/installer/>) and leave **Configure MySQL Server as a Windows Service** selected. Check that the service is running:

```powershell
Get-Service -Name 'MySQL*'
Start-Service -Name 'MySQL80'   # use the service name shown by Get-Service
```

If `mysql` is not recognised, add its `bin` folder to `PATH` (for example `C:\Program Files\MySQL\MySQL Server 8.0\bin`).

### 5. Create the databases and a least-privilege user

Edit the password in `database\setup\create-databases.sql`, then run it as the MySQL root user:

```powershell
Get-Content database\setup\create-databases.sql | mysql -u root -p
```

This creates `executive_desk` (application), `executive_desk_test` (automated tests), and the user `executive_desk@localhost`. That user only has the privileges Laravel needs on those two databases.

### 6. Configure `.env`

```powershell
Copy-Item .env.example .env
notepad .env
```

Set at least:

```dotenv
APP_URL=http://localhost:8000
APP_DEFAULT_USER_TIMEZONE=Asia/Manila   # any IANA zone

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=executive_desk
DB_USERNAME=executive_desk
DB_PASSWORD=the-password-you-chose
```

The automated tests use the `executive_desk_test` database with the same username and password (see `phpunit.xml`). They never touch `executive_desk`.

### 7. Install PHP and frontend dependencies

```powershell
composer install
npm install
```

### 8. Generate the application key

```powershell
php artisan key:generate
```

### 9. Run the migrations and seed roles

```powershell
php artisan migrate
php artisan db:seed
```

`db:seed` only creates the roles and permissions. It is safe to run again.

### 10. Optional: sample data for local development

```powershell
php artisan db:seed --class=DevelopmentSeeder
```

This creates `admin@example.test`, `assistant@example.test`, and `executive@example.test` with the password `password`, along with sample appointments, tasks, and a presentation. It refuses to run unless `APP_ENV` is `local` or `testing`. **Never use these accounts in production.**

### 11. Create the first administrator

```powershell
php artisan app:create-admin
```

The command asks for a name, email, time zone, and password. The password is typed without echo and must be at least 12 characters. After signing in, the administrator creates every other account under **Administration → Users**. Each new user receives an email link to set their own password, so administrators never handle passwords.

### 12. Build the frontend

```powershell
npm run build
```

---

## Running the application locally

Executive Desk needs four processes: the web server, the Vite dev server, a queue worker (which delivers notifications), and the scheduler (which finds due reminders and briefings).

### One command (recommended)

```powershell
composer run dev
```

This runs `php artisan dev`, which starts all four processes in one terminal (`server`, `vite`, `queue`, and `scheduler`) and restarts any that crash. Open <http://localhost:8000>. Press `Ctrl+C` to stop everything.

### Separate windows (alternative)

Open four PowerShell windows in the project folder:

```powershell
php artisan serve                       # 1. web server on http://localhost:8000
npm run dev                             # 2. Vite (hot reload)
php artisan queue:work --tries=4        # 3. queue worker
php artisan schedule:work               # 4. scheduler (runs due tasks every minute)
```

Without the queue worker and scheduler, the app still works, but no reminders or briefings are sent.

Useful one-off commands:

```powershell
php artisan reminders:dispatch          # queue reminders that are due now
php artisan briefings:dispatch          # queue briefings whose time has arrived
php artisan tasks:generate-recurring    # create upcoming recurring task occurrences
php artisan queue:failed                # list failed jobs
php artisan queue:retry all             # retry failed jobs
php artisan schedule:list               # show the schedule
```

---

## Configuring email, SMS, and push

Secrets belong only in `.env`. Never commit `.env`. After changing `.env` on a server that caches configuration, run `php artisan config:clear` (or `php artisan config:cache`).

### Email (SMTP)

For local development, the default `MAIL_MAILER=log` writes every email to `storage\logs\laravel.log` instead of sending it. To catch emails in a web inbox instead, run a local SMTP catcher such as Mailpit and set `MAIL_HOST=127.0.0.1` and `MAIL_PORT=1025`.

For real delivery, use your provider's SMTP settings:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp           # or smtps for implicit TLS on port 465
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS="executive-desk@yourcompany.com"
MAIL_FROM_NAME="Executive Desk"
```

Check the configuration under **Administration → Settings → Send test email**.

### SMS

SMS goes through a provider-independent gateway (`App\Services\Notifications\Sms\SmsGateway`).

| `SMS_DRIVER`     | Behaviour                                                                               |
| ---------------- | --------------------------------------------------------------------------------------- |
| `none` (default) | SMS is reported as **unavailable**. Reminders are marked "unavailable", never "sent".   |
| `log`            | Development only. Messages, including phone verification codes, are written to the log. |

**No real SMS provider adapter is included yet.** Choose a provider first and confirm its API, authentication, supported countries, sender ID rules, and pricing. Then add a class implementing `SmsGateway` and register it in `AppServiceProvider::register()`. The adapter must return `DeliveryResult::sent($providerMessageId)` only after the provider accepts the message. Users must verify their mobile number (by one-time code) before they can turn SMS on.

### Google sign-in (optional)

The **Sign in with Google** button appears on the sign-in page once a Google client ID and secret are configured.

1. In the Google Cloud console, open **APIs & Services → Credentials → Create credentials → OAuth client ID** and choose **Web application**.
2. Add the authorized redirect URI shown under **Administration → Settings → Sign-in page**. It has the form `{APP_URL}/auth/google/callback`, for example `http://localhost:8000/auth/google/callback`, and must match exactly, including `http` or `https` and the port.
3. Paste the client ID and secret into **Administration → Settings → Sign-in page** and click **Save Google sign-in**. The secret is stored encrypted with the application key and never shown again. You can optionally restrict sign-in to one Google Workspace domain.

Alternatively, put the credentials in `.env` and run `php artisan config:clear`. Values saved on the settings page take precedence over `.env`:

    ```dotenv
    GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
    GOOGLE_CLIENT_SECRET=your-client-secret
    GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
    GOOGLE_HOSTED_DOMAIN=yourcompany.com   # optional: only accept this Google Workspace domain
    APP_SUPPORT_EMAIL=it@yourcompany.com   # optional: "Contact your administrator" link
    ```

Google sign-in never creates accounts. The Google account's email must be verified by Google and must match an active account that an administrator created. The first successful sign-in links the Google identity to the account; after that, only that Google identity can sign in to it. Users with two-factor authentication still complete the two-factor challenge.

### Sign-in page image (optional)

Administrators can upload a photograph for the sign-in page's brand panel under **Administration → Settings → Sign-in page → Background image** (JPG, PNG, or WebP, up to 5 MB, at least 800 × 600 px; 1600 px or wider looks best). **Use default** returns to the dark gradient. The image is stored privately and served by the app, so `storage:link` is not needed. As a fallback, a file saved as `public/images/login-hero.jpg` is used when nothing has been uploaded.

### Web Push (PWA)

1. Generate a VAPID key pair once:

    ```powershell
    php artisan push:vapid
    ```

2. Copy the two printed lines into `.env` and set a contact address:

    ```dotenv
    VAPID_SUBJECT="mailto:it@yourcompany.com"
    VAPID_PUBLIC_KEY=...
    VAPID_PRIVATE_KEY=...
    ```

3. Rebuild the frontend (`npm run build`). Each user then opens **Settings → Notifications → Enable push on this device**.

Notes:

- Push and the service worker need **HTTPS**. Browsers make one exception for `http://localhost`.
- On iPhone and iPad, users must first **Add to Home Screen**, then enable push from the installed app.
- "Sent" means the push service accepted the message. The device's browser, operating system, focus mode, and battery settings decide whether and when it appears.
- Changing the VAPID keys invalidates every existing subscription; users must enable push again.
- The service worker is registered only in production builds (`npm run build`), not under `npm run dev`.

---

## Testing

```powershell
php artisan test --compact     # backend feature tests (MySQL database executive_desk_test)
npm test                       # frontend unit tests (Vitest via Vite+)
npm run types:check            # TypeScript
npm run check                  # lint and formatting
vendor\bin\pint --test         # PHP code style
vendor\bin\phpstan analyse     # static analysis (Larastan, level 7)
```

Backend tests cover authentication and roles, authorization, appointment CRUD and validation, conflict detection, time-zone and DST conversion, and task CRUD, assignment, and completion. They also cover recurring tasks, presentation and file authorization, reminder generation, regeneration and cancellation, duplicate prevention, and notification preferences. Delivery tests cover queue failures with retry and backoff, recovery of stuck reminders, and SMS and push unavailability. The remaining tests cover briefings, report and export authorization, offline sync, and a render check of every page per role. Frontend tests cover time-zone formatting and calendar layout.

---

## How reminders work

1. Saving an appointment, task, or presentation writes one **reminder row** per recipient, channel, and interval, each with a deterministic idempotency key. A unique index makes duplicates impossible, even when saves run concurrently.
2. Rescheduling produces new keys. Pending reminders for the old time are cancelled, and reminders for the new time are created (or revived, if the appointment moved back).
3. Every minute, `reminders:dispatch` claims due reminders with a conditional update (`pending → queued`) and queues one job per reminder. It skips reminders more than 30 minutes late, re-queues reminders whose job was lost, and fails reminders that were interrupted mid-send. Those are not resent, because the provider may already have accepted them.
4. Before sending, the job checks that the reminder still matches the record's current time and status and that the recipient still allows the channel. Transient failures are retried with exponential backoff (1, 5, then 15 minutes) up to 4 attempts. Every attempt is written to the delivery history.

External providers cannot guarantee exactly-once delivery. The design minimizes duplicates and records what happened; it does not claim more.

---

## Production deployment

The recommended target is Ubuntu 24.04 with Nginx, PHP-FPM 8.4, and MySQL 8, or Laravel Cloud.

```bash
git clone <repository> /var/www/executive-desk && cd /var/www/executive-desk
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate   # then edit .env
php artisan migrate --force
php artisan db:seed --force                         # roles and permissions only
php artisan app:create-admin
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Production `.env` essentials:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://desk.yourcompany.com
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
LOG_LEVEL=warning
```

- **Web server:** Point Nginx at `public/`, serve only over HTTPS, and set `client_max_body_size 30M;`.
- **Queue worker:** Keep one running with Supervisor:

    ```ini
    [program:executive-desk-worker]
    command=php /var/www/executive-desk/artisan queue:work --tries=4 --timeout=110 --sleep=3 --max-time=3600
    autostart=true
    autorestart=true
    user=www-data
    numprocs=2
    ```

    The worker `--timeout` must stay below `DB_QUEUE_RETRY_AFTER` (150 seconds in `.env.example`).

- **Scheduler:** Add one cron entry for the web user:

    ```cron
    * * * * * cd /var/www/executive-desk && php artisan schedule:run >> /dev/null 2>&1
    ```

- **On every deploy:** `php artisan migrate --force`, re-cache the configuration, then `php artisan queue:restart`.
- **Database user:** Use the least-privilege `executive_desk` user, never `root`.
- **Backups:** Back up the database and `storage/app/private` (uploaded documents).

**Windows Server alternative:** Run IIS with PHP via FastCGI (or Nginx for Windows), with the document root at `public\`. Run the queue worker as a Windows service with NSSM (`nssm install ExecutiveDeskWorker "C:\Program Files\php\php.exe" "artisan queue:work --tries=4 --timeout=110"` with the app folder as the startup directory). For the scheduler, create a Task Scheduler task that runs `php artisan schedule:run` in the app folder every minute.

---

## Troubleshooting

| Symptom                                                  | Fix                                                                                                                                                    |
| -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `could not find driver`                                  | Enable `extension=pdo_mysql` in `php.ini` (check `php --ini` for the loaded file).                                                                     |
| `SQLSTATE[HY000] [1045] Access denied`                   | Check `DB_USERNAME` and `DB_PASSWORD` in `.env`, then run `php artisan config:clear`.                                                                  |
| `SQLSTATE[HY000] [2002]` (connection refused)            | MySQL is not running: `Start-Service MySQL80`.                                                                                                         |
| `Unknown database 'executive_desk'`                      | Run the SQL script in installation step 5.                                                                                                             |
| `Specified key was too long`                             | Use MySQL 8 or later with `utf8mb4` (the default).                                                                                                     |
| `'php' is not recognized` / `'mysql' is not recognized`  | Add the folder to `PATH` and open a new PowerShell window.                                                                                             |
| `running scripts is disabled on this system` (npm)       | Run `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.                                                                                             |
| Composer: `ext-intl` / `ext-gmp` missing                 | Enable `intl` and `gmp` in `php.ini`. Web Push needs `gmp` (or `bcmath`).                                                                              |
| `Unable to locate file in Vite manifest`                 | Run `npm run build`, or keep `npm run dev` running.                                                                                                    |
| Uploads fail at about 2 MB                               | Raise `upload_max_filesize` and `post_max_size` in `php.ini`, then restart `php artisan serve`.                                                        |
| Permission errors writing logs or cache                  | Make sure your Windows user can write to `storage\` and `bootstrap\cache\`. If the project is in a OneDrive-synced folder, move it to `C:\projects`.   |
| Reminders never arrive                                   | Check that the queue worker and scheduler are running. **Administration → Settings → Delivery health** shows the scheduler's last run and failed jobs. |
| Emails do not arrive                                     | With `MAIL_MAILER=log`, look in `storage\logs\laravel.log`. Otherwise use **Send test email** and check the delivery history for the sanitized error.  |
| "Push notifications have not been configured"            | Run `php artisan push:vapid`, add the keys to `.env`, then `npm run build`.                                                                            |
| Signed out right after login                             | `SESSION_SECURE_COOKIE=true` needs HTTPS. Set it to `false` for `http://localhost`.                                                                    |
| Tests fail with `Unknown database 'executive_desk_test'` | Run the SQL script in installation step 5. Tests never use the main database.                                                                          |

---

## Project status

**Complete and covered by automated tests:** every module in the [features](#features) table: authentication and roles, calendar, tasks (including recurrence), presentations and files, the reminder engine, email delivery, notification preferences and history, phone verification, Web Push, briefings, reports and exports, offline snapshot and sync, the audit log, and administration.

**Needs configuration or a decision before production:**

- **SMS:** The abstraction, verification flow, and "unavailable" reporting are done. A real provider adapter has not been written because no provider has been chosen.
- **SMTP and VAPID credentials:** These must be supplied in `.env`. Check them with the built-in test email and test push.
- **Push delivery:** This has only been verified against a fake gateway in tests. Test it on your target browsers and devices after configuring VAPID over HTTPS.

**Known limitations:**

- Calendar appointments do not repeat; recurring tasks do.
- SMS delivery receipts (post-acceptance status) are not reconciled; the history shows provider acceptance only.
- Frontend tests cover pure logic. Component tests would need `jsdom` and `@testing-library/react`, which are not installed.
- Offline support covers viewing the snapshot and queuing task status changes only. Other edits need a connection.
