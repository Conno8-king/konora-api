<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Deploying to Render

This backend ships with a minimal `Dockerfile` and `docker/start.sh` so it can be deployed to [Render](https://render.com) as a Docker-based Web Service.

### Service setup

1. In the Render dashboard, create a new **Web Service** from this repository.
2. Set the **Root Directory** to `konora-backend` so Render builds from this folder.
3. Choose **Docker** as the runtime. Render will detect the `Dockerfile` automatically.
4. Leave the **Build Command** empty and let the Dockerfile handle the build. The container starts via `docker/start.sh`, which binds to Render's `$PORT`.

### Required environment variables

Set these in the Render service's **Environment** tab:

- `APP_NAME` — display name, e.g. `Konora`.
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` — generate locally with `php artisan key:generate --show` and paste the value.
- `APP_URL` — the public URL Render assigns (e.g. `https://konora-backend.onrender.com`).
- `LOG_CHANNEL=stack`
- `FRONTEND_URL` — the deployed frontend URL (used by Sanctum and Paystack callback links).

### Database

Provision a managed **PostgreSQL** instance on Render and link these variables from its connection info:

- `DB_CONNECTION=pgsql`
- `DB_HOST`
- `DB_PORT=5432`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

If you instead want to use SQLite for a quick smoke test, set `DB_CONNECTION=sqlite` and `DB_DATABASE=/var/www/html/database/database.sqlite`. Note that SQLite data does not persist across deploys on Render's free disk-less services.

### Sessions, cache, and queue

Render's filesystem is ephemeral, so prefer database-backed drivers (already the defaults in `.env.example`):

- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database`

### Paystack

- `PAYSTACK_SECRET_KEY`
- `PAYSTACK_PUBLIC_KEY`
- `PAYSTACK_CALLBACK_URL` — e.g. `https://your-frontend.example.com/checkout/callback`
- `PAYSTACK_BASE_URL=https://api.paystack.co`
- `PAYSTACK_MOCK=false` in production.

### Startup behaviour

`docker/start.sh` runs on every deploy and:

- Ensures Laravel's `storage/` and `bootstrap/cache/` directories exist and are writable.
- Caches config/routes/views when `APP_ENV=production`.
- Runs `php artisan migrate --force` (disable with `RUN_MIGRATIONS=false`).
- Creates the public storage symlink (disable with `RUN_STORAGE_LINK=false`).
- Serves the app via `php artisan serve` on `0.0.0.0:$PORT`.
