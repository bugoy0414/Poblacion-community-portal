# Barangay Community Portal

A Laravel 12 application for submitting and managing barangay community reports.
Production deployment uses Render for the web service, Supabase PostgreSQL for
the database, and a private Supabase Storage bucket for report photos.

## Deploy with Render and Supabase

### 1. Prepare Supabase

1. Create a Supabase project and open **Project Settings → Database → Connect**.
2. Use the **Session pooler** connection details for Render. The direct database
   endpoint may require IPv6, while the pooler is suitable for external hosts.
   Keep the pooler host, port, database, username, and database password.
3. In **Storage**, create a **private** bucket named `report-photos`.
4. Create S3 access keys for the project in **Storage → S3 Connection**. Keep
   the access key and secret private; they grant server-side access to storage.

### 2. Create the Render service

1. Push this repository to GitHub and select **New → Blueprint** in Render.
2. Select the repository and let Render read [`render.yaml`](./render.yaml).
   The blueprint builds the Docker image, runs migrations and safe production
   defaults before deployment, and checks the `/up` health endpoint.
3. Enter the requested environment values:
   - `APP_KEY`: generate a fresh key with `php artisan key:generate --show`.
     Keep it stable after deployment; changing it invalidates encrypted data
     and sessions.
   - `APP_URL`: the HTTPS Render URL for this service.
   - `DB_HOST`, `DB_USERNAME`, and `DB_PASSWORD`: the Supabase **Session pooler**
     values. Keep `DB_PORT` as `5432`, `DB_DATABASE` as `postgres`, and TLS
     enabled with `DB_SSLMODE=require`.
   - `SUPABASE_S3_ENDPOINT`: `https://<project-ref>.supabase.co/storage/v1/s3`.
   - `SUPABASE_S3_ACCESS_KEY` and `SUPABASE_S3_SECRET_KEY`: the S3 credentials
     from Supabase. Keep the region as `us-east-1` and bucket as `report-photos`.

Do not put production credentials in `.env`, source control, or this README.
Render injects these settings into the service at runtime.

### 3. Bootstrap the first administrator

After the first deploy, register your own account through the app. In the
Supabase SQL Editor, grant that account staff-admin access by replacing the
email below with the address you registered:

```sql
INSERT INTO public.user_roles (user_id, role, created_at, updated_at)
SELECT id, 'admin', now(), now()
FROM public.users
WHERE email = 'your-admin@example.com'
ON CONFLICT (user_id, role) DO NOTHING;
```

Sign in at `/staff/login`, open Admin, and replace the seeded placeholder
hotline, office hours, acknowledgment target, and privacy notice before inviting
residents to use the service. Review the privacy notice and data handling with
the appropriate local privacy officer.

The production seeder intentionally creates no accounts or example reports.
Do not run the general `DatabaseSeeder` against production; it is intended for
local/testing sample data.

## Local development

Requirements: PHP 8.2+, Composer, Node.js, and npm.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

The default local configuration uses SQLite and local private file storage.
Run `composer test` for the test suite.
