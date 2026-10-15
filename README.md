# Library Book Monitoring System

A Laravel application for managing library users, the book catalog, physical-copy inventory, and borrowing activity.

## Run locally

Requirements: PHP 8.3+, Composer, Node.js, and npm.

From the project directory:

```powershell
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run dev
```

In a second terminal, start the web server:

```powershell
php artisan serve
```

Open `http://127.0.0.1:8000`. For Laragon, start the project through Laragon or use `http://lbms.test` if its virtual host is enabled.

The default local configuration uses SQLite. Make sure `DB_CONNECTION=sqlite` is set in `.env`. If `database/database.sqlite` does not exist, create the empty file before running migrations:

```powershell
New-Item -ItemType File database\database.sqlite
```

The development seeder creates `admin@example.com`, `staff@example.com`, and `student@example.com`, all with the initial password `password`. Change these credentials before using the app beyond a local demo.

## Main features

- Role-based administrator, staff, and student accounts.
- Book create, read, update, delete, search, and section filtering.
- Genre-aware book records, catalog filtering, and original stories in the seeded catalog.
- Barcode/ISBN search in the student and staff catalog; USB barcode scanners can enter a code into the focused search field.
- Copy counts and live availability calculated from unreturned loans, with title-level available, maintenance, damaged, and lost condition states.
- Staff/admin issue and return workflow, due dates, overdue status, searchable loan history, and overdue fines.
- Students can reserve books that are temporarily unavailable, cancel pending requests, and view their reservation queue. Staff/admin can issue returned copies in request order.
- Daily due-soon and overdue email reminders, plus reservation-fulfillment notifications.
- Printable library reports and CSV exports for the catalog and loan records.
- Student reading history, favorites, and genre-based book recommendations.
- Admin activity log for catalog, user, loan, reservation, and database-backup actions.
- Private SQLite database backups with integrity-checked restore and an automatic pre-restore safety snapshot.
- An administrator-controlled login banner image, with validated image uploads and a default illustration fallback.
- Students can browse and search titles and see only their own borrowing history and outstanding fines.
- Dashboards show current and overdue loan counts.
- JSON API access through expiring, revocable bearer tokens.
- Weather on dashboards with city search, a five-day forecast, short-term caching, and recently saved data as a fallback during outages.

## JSON API

Get a bearer token by posting a user's email and password to `POST /api/auth/token` with JSON fields `email`, `password`, and an optional token `name`. Tokens expire after 30 days; the plaintext token is returned only once and only its SHA-256 hash is stored. Send it as `Authorization: Bearer <token>`. Token issuance is rate limited. Password changes revoke that user's existing tokens.

Available authenticated endpoints:

| Method | Endpoint | Access |
| --- | --- | --- |
| `GET` | `/api/categories` | Any valid token |
| `GET` | `/api/books` | Any valid token; supports `search` (title, author, genre, or barcode), `category`, and `genre` |
| `GET` | `/api/books/{slug}` | Any valid token |
| `GET` | `/api/loans` | Students see their own records; staff/admin see all |
| `POST` | `/api/loans` | Students borrow for themselves; staff/admin issue to a student |
| `POST` | `/api/loans/{id}/return` | Staff/admin |
| `GET` | `/api/reports/loans` | Staff/admin |
| `DELETE` | `/api/auth/token` | Revokes the current token |

Book search accepts an exact barcode/ISBN as well as title, author, and genre terms. The barcode represents a title-level code. Condition status also applies to all copies recorded for a title; tracking different conditions on individual physical copies requires individual-copy inventory records.

## Notifications and database care

Loan reminders are scheduled daily at 8:00 AM. In local development, run `php artisan schedule:work`; production deployments must configure Laravel's scheduler (`php artisan schedule:run` every minute). Set a real mail transport in `.env` for delivery; the local `log` mailer records messages instead of sending them.

Administrators can create, download, and restore SQLite backups from **Backups** in the dashboard. Backups are stored under private application storage, not the public web root. Restore is available only for persistent SQLite databases and rejects files that fail integrity, foreign-key, or application-schema checks.

Administrators can change the image displayed above the login form from **Login image** in the dashboard. Uploads are limited to JPG, PNG, or WebP (up to 5 MB and 2400 × 1600 pixels); the built-in illustration remains the default and reset option.

Staff/admin can access **Reports** for a print-friendly summary and CSV exports. Students can open **My reading** to review recent views and receive genre-based suggestions.

The catalog seeder supplies original story content created for this project; it does not include copyrighted manga chapters or artwork.

## Weather configuration

The weather service uses Open-Meteo and does not require an API key. Configure the default location in `.env`:

```dotenv
WEATHER_CITY=Manila
WEATHER_LATITUDE=14.5995
WEATHER_LONGITUDE=120.9842
WEATHER_API_TIMEOUT=5

LIBRARY_FINE_PER_DAY=15.00
```

The overdue fine rate is charged once per late day and can be adjusted in `.env`.

Custom city searches use Open-Meteo geocoding. Weather results are refreshed after ten minutes and retained for up to six hours so the dashboard can show the last successful result during a temporary provider outage.

## Tests

Run the test suite with:

```powershell
php artisan test
```
