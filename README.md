# FurniCraft

FurniCraft is a Laravel web application for Lance Furniture Shop. It gives authenticated customers a guided journey through catalog browsing, saved preferences, computer-vision room analysis, hybrid furniture recommendations, scoped Gemini chat, 2D room planning, and reservation requests. Administrators manage catalog records, customer access, reservations, and confirmation notifications.

## Scope

- Exactly two roles: Customer and Administrator/Owner
- Authentication is required for every customer and administrator feature; there is no guest browsing mode
- The enforced customer flow is login → browse and save preferences → upload and analyze a room → generate recommendations and chat → submit a reservation request
- Content-based and collaborative recommendation scoring with an optional Gemini-generated explanation
- Gemini-assisted chatbot restricted to the current recommendation and Lance Furniture Shop catalog facts
- Uploaded room-image analysis can estimate width and depth with Gemini, accepts customer measurements as the authoritative override, and feeds the draggable 2D furniture overlay
- Reservation requests only; no payment, delivery tracking, inventory, or financial management
- Queued confirmation and password-reset notifications with an audit log
- Password-reset codes stored as hashes, valid for 15 minutes, single-use, and request-rate-limited
- Responsive Bootstrap interface for phone and desktop browsers

## Requirements

- PHP 8.2 or newer
- Composer 2
- MySQL/MariaDB through XAMPP for the intended local deployment
- PHP extensions normally enabled by XAMPP for Laravel (`pdo_mysql`, `mbstring`, `openssl`, `fileinfo`)
- Internet access for Gemini and the Bootstrap CDN

The checked-in local `.env` uses SQLite so the demo works immediately in this workspace. `.env.example` is configured for the required MySQL database.

## XAMPP and MySQL setup

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Create a database named `furnicraft` with `utf8mb4` encoding.
3. Copy `.env.example` to `.env` and confirm these values:

```env
APP_NAME=FurniCraft
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=furnicraft
DB_USERNAME=root
DB_PASSWORD=
```

4. Install and initialize the application:

```powershell
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

5. Start the application and queue worker in separate terminals:

```powershell
php artisan serve
php artisan queue:work --tries=3
```

Open `http://127.0.0.1:8000`.

## Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Customer | `demo@furnicraft.local` | `password` |
| Administrator/Owner | `admin@furnicraft.local` | `password` |

Change both passwords before using the application outside a local demonstration.

## Gemini configuration

The recommendation algorithm, chatbot, and room workflow have deterministic local fallbacks, so the system remains usable without an API key. To enable Gemini, add these values to `.env`:

```env
GEMINI_API_KEY=your_key_here
GEMINI_MODEL=gemini-2.5-flash
```

The model name is configurable so it can be changed without modifying application code. The hybrid recommendation ranking is computed by FurniCraft; Gemini receives the ranked, in-system product facts to explain results. Chat prompts explicitly limit answers to the supplied catalog and recommendation data.

## Email configuration

Laravel Notifications implement both required email flows and use the configured queue. For local inspection, the default `log` mailer writes messages to `storage/logs/laravel.log`. Configure a real SMTP provider for deployment:

```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="FurniCraft"
QUEUE_CONNECTION=database
```

Keep `php artisan queue:work` running so queued mail is delivered.

## Test and quality commands

```powershell
php artisan test
php artisan view:cache
vendor\bin\pint --test
```

The feature suite verifies the login-only access model, required preferences and room analysis, safe post-login return, role authorization, partial-word autocomplete, hybrid recommendations, Gemini dimension estimates, catalog-only room previews, hashed reset codes, rate limiting, and reservation-confirmation notifications.

## Important privacy notes

- Do not place people, documents, addresses, or other personal material in room photos.
- Reset codes are never stored in plaintext.
- Individual view and favorite events are used for recommendations and aggregate ranking; the customer interface does not expose another customer’s activity.
- Configure HTTPS, production mail, backups, retention rules, and a privacy notice before real customer use.
