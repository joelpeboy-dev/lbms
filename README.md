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

Laravel Sanctum protects the API with personal access tokens. Register at `POST /api/register` with `name`, `email`, `password`, and `password_confirmation`, or log in at `POST /api/login` with `email` and `password`. Both endpoints return a bearer token that expires after 30 days; only its SHA-256 hash is stored. Send it as `Authorization: Bearer <token>`. Login and registration are rate limited, and password changes revoke the user's existing tokens. The legacy `POST /api/auth/token` endpoint remains an alias for login.

Available authenticated endpoints:

| Method | Endpoint | Access |
| --- | --- | --- |
| `POST` | `/api/register` | Public; creates a student account and returns a bearer token |
| `POST` | `/api/login` | Public; submit email and password to receive a bearer token (rate limited) |
| `POST` | `/api/logout` | Authenticated; revokes the current token |
| `GET` | `/api/user` | Authenticated; returns the current user's profile |
| `POST` / `DELETE` | `/api/auth/token` | Backward-compatible login and logout aliases |
| `GET` | `/api/categories` | Any valid token |
| `GET` | `/api/books` | Any valid token; supports `search` (title, author, genre, or barcode), `category`, and `genre` |
| `GET` | `/api/books/{slug}` | Any valid token |
| `POST` | `/api/books` | Staff/admin; creates a catalog record |
| `PUT` | `/api/books/{slug}` | Staff/admin; updates required catalog fields |
| `PATCH` | `/api/books/{slug}` | Staff/admin; updates supplied fields |
| `DELETE` | `/api/books/{slug}` | Staff/admin; blocked when the title has loan or reservation history |
| `GET` | `/api/students` | Staff/admin; lists student accounts |
| `POST` | `/api/students` | Staff/admin; creates a student account |
| `GET` | `/api/students/{id}` | Staff/admin; reads a student account |
| `PUT` / `PATCH` | `/api/students/{id}` | Staff/admin; updates a student account |
| `DELETE` | `/api/students/{id}` | Staff/admin; blocked when the student has loan or reservation history |
| `GET` | `/api/loans` | Students see their own records; staff/admin see all |
| `GET` | `/api/transactions` | Current loan status records; students see their own, staff/admin see all |
| `POST` | `/api/loans` | Students borrow for themselves; staff/admin issue to a student |
| `POST` | `/api/loans/{id}/return` | Staff/admin |
| `GET` | `/api/reports/loans` | Staff/admin |

`book_transactions` stores one current status row per loan, linked to `book_loans`. Returning a book updates that row; voiding a loan removes it. The loan record and audit log retain the loan and void history. The internal API uses the same URL for all four methods: `http://localhost:8000/api/internal/transactions`. Internal endpoints also require a Sanctum bearer token; transaction operations require a staff/admin account.

| Method | Endpoint | Access |
| --- | --- | --- |
| `GET` | `/api/internal/transactions` | List current loan status records; add `?id={id}` to read one |
| `POST` | `/api/internal/transactions` | Borrow/issue a book; send `book_id`, `borrower_id`, and `due_at` |
| `PATCH` | `/api/internal/transactions` | Return a book; send the transaction `id` in the JSON body |
| `DELETE` | `/api/internal/transactions` | Void an active loan; send the transaction `id` in the JSON body |

`GET` with an `id` query returns a single record. `PATCH` and `DELETE` use a JSON body such as `{"id":1}`. `DELETE` releases the copy and removes the current status row; the loan and audit records remain available for history.

For local testing, send `POST http://127.0.0.1:8000/api/login` a JSON body such as `{"email":"staff@example.com","password":"password"}`. Include `Accept: application/json` and `Authorization: Bearer <token>` when calling protected endpoints. Missing, invalid, and revoked tokens return JSON `401` responses; validation errors return `422`.

Book create/update requests accept `category`, `title`, `author`, `description`, `content`, `total_copies`, and `condition_status`. Optional fields are `genre`, `cover_url`, `illustrations`, `source_label`, `source_url`, and `barcode`. The API generates each book's slug. In Postman or another HTTP client, include `Accept: application/json` and `Authorization: Bearer YOUR_TOKEN`. Creation returns `201`; updates return `200`; invalid data returns `422`; missing titles return `404`; and deleting a title with loan or reservation history returns `409`. Book responses include catalog metadata but do not expose full book content.

The Students API uses the existing `users` table and only manages accounts whose role is `student`; it does not create a duplicate students table. Student records include `student_id`, `first_name`, `last_name`, `email`, `course`, and `year_level`. Existing accounts can have empty student details. A successful `POST /api/students` creates an account with a securely generated random password that is never returned; the student must use the existing password-reset flow or an administrator must set a password before sign-in. For example, send `POST /api/students` this JSON body:

```json
{
  "student_id": "2026-0001",
  "first_name": "Juan",
  "last_name": "Dela Cruz",
  "email": "juan.delacruz@example.com",
  "course": "BS Information Technology",
  "year_level": 2
}
```

The Students API returns `201` on creation, `200` on successful reads and updates, `422` for invalid input, `404` for missing or non-student IDs, and `409` if deletion would remove a student with loan or reservation history.

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
