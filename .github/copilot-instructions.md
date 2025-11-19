<!--
Project-specific Copilot instructions for the exccdiport01 repository.
Keep these concise and actionable so AI coding agents are productive immediately.
--> 

# Quick Project Summary

- Framework: Laravel backend (PHP 8.2+, Laravel v12) + Inertia + Vue 3 frontend (Vite).
- Purpose: Student fees, transactions, and accounting workflows (see `routes/web.php`).

# Where to look first

- Backend entry points: `routes/web.php`, `app/Http/Controllers/`, `app/Models/`.
- Business logic/services: `app/Services/` (e.g. `FeeAssignmentService.php`, `AccountService.php`).
- Authorization: `app/Policies/` (notably `StudentFeePolicy.php`) and `app/Providers/AuthServiceProvider.php`.
- Frontend (Inertia pages): `resources/js/pages/` (Inertia pages referenced by `Inertia::render()` calls).
- Config & env: `config/*.php`, `.env` (not committed). Tests: `phpunit.xml` (uses in-memory sqlite).

# Architecture & conventions (important for code changes)

- Role-based access: routes use `role:` middleware (see `app/Http/Middleware/RoleMiddleware.php`) and a typed enum `App\Enums\UserRoleEnum`.
- Services contain core domain operations (e.g. assigning fees and recalculating balances). Prefer adding domain logic inside `app/Services` rather than controllers.
- Transactions model financial activity. `Transaction` rows have `kind` (`charge`/`payment`), `status` (`pending`/`paid`), `meta` JSON for contextual data.
- Inertia is used to transport server state to Vue pages. Shared props are set in `AppServiceProvider::boot()` via `Inertia::share`.
- Authorization uses Policies (see `StudentFeePolicy`) and sometimes enum values — policies defensively accept both enum and string role types.

# Developer workflows & commands

- Full dev stack (recommended): `composer run dev` — runs `php artisan serve`, queue listener and `npm run dev` concurrently (see `composer.json` `dev` script).
- Frontend only: `npm run dev` (Vite dev server).
- Build frontend assets: `npm run build` (or `npm run build:ssr` for SSR builds).
- PHP tests: `composer test` (runs `php artisan test` / phpunit). PHPUnit config uses in-memory sqlite so CI/local tests should not require a database file.
- Common artisan tasks: `php artisan migrate --seed`, `php artisan queue:listen`, `php artisan config:clear`.

# Patterns & examples to follow

- Add domain logic to `app/Services` and call from controllers (example: `FeeAssignmentService::assignFeesToStudent`).
- Update account balances via `AccountService::recalculate($user)` after changing transactions.
- Use `Transaction::create([...])` for charges/payments and include `meta` when extra context is useful (see `FeeAssignmentService`).
- Route protection: prefer route middleware + policies. Example: `Route::middleware(['auth','verified','role:admin,accounting'])->group(...)`.

# Testing & CI notes

- Unit and feature tests live in `tests/Unit` and `tests/Feature`. PHPUnit environment is set in `phpunit.xml` to use sqlite in-memory.
- CI workflows exist under `.github/workflows/` for tests and linting. Use `composer test` and `npm run lint` locally to mirror CI.

# Formatting & linting

- JavaScript/TypeScript: `prettier` and `eslint` (`npm run format`, `npm run lint`).
- PHP: project includes `laravel/pint` in `require-dev` — follow existing PHP style.

# Integration points & external deps

- Ziggy (`tightenco/ziggy`) for route helpers between Laravel and Vue (`resources/js/ziggy.js`).
- Inertia (`inertiajs/inertia-laravel` + `@inertiajs/vue3`) for server-driven SPA navigation.
- DOMPDF used for PDF exports (`barryvdh/laravel-dompdf`) — export code is referenced in `StudentFeeController::exportPdf`.

# When editing code, quick checklist

- Run related tests (`composer test`) for backend changes.
- If changing frontend or Inertia props, run `npm run dev` and check the matching `resources/js/pages/` component.
- If you change DB schema, update migrations (`database/migrations`) and add seeders under `database/seeders`.

# If you need more context

- Open `routes/web.php`, `app/Services/*`, `app/Policies/*`, and example pages in `resources/js/pages/Students`.
- Ask for the environment `.env` values (not committed) if a runtime DB or mailer is required.

---
If any section is unclear or you want more examples (e.g., a walkthrough of fee assignment flow), tell me which area to expand.
