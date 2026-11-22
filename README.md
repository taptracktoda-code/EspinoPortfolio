# Personal Portfolio and Credentials Management System

A Laravel 12 and Inertia application with React, TypeScript, Tailwind CSS, a public portfolio, and a private owner dashboard. The database starts empty. Public pages show only published records, and uploaded media stays in private storage behind Laravel access checks.

## Local setup

Requires PHP 8.2 or newer with GD and SQLite (or MySQL), Composer, and Node 22.

```sh
composer install
npm ci
# Copy .env.example to .env if needed, then create database/database.sqlite for SQLite.
php artisan key:generate
php artisan migrate
php artisan portfolio:owner owner@example.com
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000` for the portfolio and `/admin/login` for the dashboard. The owner command prompts for the password without echoing it. It supports `--reset` for the same login address. The profile form controls which profile details are public. Each portfolio entry can be saved as a draft or published. Publishing a resume automatically makes the previous version private. `php artisan serve` applies the project’s upload limits. If you serve the app through Apache or another local web server, set `upload_max_filesize = 10M` and `post_max_size = 48M` in that server’s PHP configuration.

Run the email worker in another terminal with `php artisan queue:work`. Set `MAIL_MAILER=array` for disposable local testing or configure a real transport to deliver mail. The dashboard tracks queue and mail transport status. “Sent” means the configured transport accepted the message. Client email replies arrive at the configured reply-to mailbox. The dashboard stores the web inquiry and owner replies.

To change the owner login email, run `php artisan portfolio:owner new-email@example.com --change-email`. Sign in again with your current password.

Run `php artisan test`, `npm run typecheck`, and `npm run build` to verify changes. The service worker runs with the production frontend build on HTTPS or localhost. Visit a public page online after installation so it is available offline. Private routes and PDFs require connectivity. Previously saved public content can be shown until the browser reconnects and refreshes it.

## Deploy to Railway

1. Push this source to the intended GitHub repository and link the current Railway CLI to a dedicated Railway project. Add the shared environment variables `APP_KEY`, `APP_URL`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, and `MAIL_REPLY_TO_ADDRESS` in Railway. Use `php artisan key:generate --show` to generate the application key.
2. Run `railway config plan` and review the four resources before applying with `railway config apply`. The configuration defines a web service, an email worker, MySQL, and a private uploads bucket. It selects Singapore for the web service, worker, and bucket. [Railway Infrastructure as Code](https://docs.railway.com/infrastructure-as-code)
3. Connect both compute services to the same GitHub repository and branch in their Service Source settings. The Dockerfile builds both services. Generate a public domain for **Portfolio Web** and set the shared `APP_URL` to the resulting HTTPS URL. Keep **Portfolio Worker** private and deploy the web service first to initialize the queue tables. [Railway service sources](https://docs.railway.com/services)
4. Open a shell in the web service with Railway SSH, then run `php artisan portfolio:owner owner@example.com`. Sign in and publish the actual profile and portfolio content.
5. Enable scheduled [database backups](https://docs.railway.com/volumes/backups) or [point-in-time recovery](https://docs.railway.com/volumes/point-in-time-recovery) and arrange independent backups of the uploads bucket. Monitor `/up`, worker logs and queue failures. Test an inquiry, a reply to a mailbox you control, a published document and a redeployment that preserves uploads.

For dashboard-only setup, create the MySQL service and private bucket and use the environment variables below. Set the web pre-deploy command to `php artisan migrate --force`, the web health check to `/up`, and the worker start command to `php artisan queue:work database --queue=default --sleep=3 --timeout=45`. Match the database and bucket references to the service names. [Railway MySQL](https://docs.railway.com/databases/mysql) · [Railway Storage Buckets](https://docs.railway.com/storage-buckets)

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_KEY=<value from php artisan key:generate --show>
LOG_CHANNEL=stderr
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=${{PortfolioBucket.ACCESS_KEY_ID}}
AWS_SECRET_ACCESS_KEY=${{PortfolioBucket.SECRET_ACCESS_KEY}}
AWS_DEFAULT_REGION=${{PortfolioBucket.REGION}}
AWS_BUCKET=${{PortfolioBucket.BUCKET}}
AWS_ENDPOINT=${{PortfolioBucket.ENDPOINT}}
AWS_USE_PATH_STYLE_ENDPOINT=false
MAIL_MAILER=resend
RESEND_API_KEY=<Resend API key>
MAIL_FROM_ADDRESS=portfolio@your-verified-domain.example
MAIL_FROM_NAME="Personal Portfolio"
MAIL_REPLY_TO_ADDRESS=owner@your-domain.example
```

Use a verified sending domain with Resend. Railway also supports SMTP on eligible plans. [Railway outbound email](https://docs.railway.com/networking/outbound-networking)

Failed jobs can be inspected with `php artisan queue:failed` and retried with `php artisan queue:retry <uuid>` after fixing the transport. Recover the database and upload bucket together to retain document references.
