# Klinic 360 — Repository Memory

## Project Overview
Klinic 360 is an AI-powered multi-tenant B2B SaaS clinic operating system for Ayurveda, Siddha & Homeopathy practices.
Built as a modular Laravel monolith. Two plans: Solo Doctor ₹999/mo, Small Clinic ₹1,999/mo.

## Tech Stack
- PHP 8.4 / Laravel 13 (>=12 required by spec)
- Livewire 3 + Alpine.js + Tailwind CSS 4 + Vite 8
- Database: SQLite for dev/test (MySQL/MariaDB-compatible for prod)
- Auth: Laravel sessions + Sanctum (API)
- Queue: database driver

## Build Commands
- `npm run build` — compile frontend assets (Vite)
- `npm run dev` — Vite dev server with HMR
- `php artisan migrate --graceful` — run migrations (SQLite by default)
- `php artisan serve` — dev server on :8000
- `php artisan test` — run Pest test suite
- `composer test` — same

## Architecture (Document 2 §3-4)
- `app/Services/{AI,Appointments,Billing,Documents,IPD,Notifications,Patients,Payments,Reports,Subscriptions,Telemedicine,Tenancy,Treatments}` — business logic
- `app/Contracts/` — provider interfaces (AI, Payment, Subscription, Video, WhatsApp, SMS, Email, Storage, OCR, Speech)
- `app/Integrations/` — concrete provider implementations
- `app/Policies/` — authorization
- `app/Livewire/` — Livewire components
- Business logic in Services, integrations behind interfaces, validation in Form Requests, authorization in Policies.

## Multi-Tenancy (Document 2 §5)
- `tenants` table; every tenant-owned table has `tenant_id`
- Isolation via middleware + global scopes + scoped queries + route/model binding
- NEVER trust client-supplied `tenant_id`

## RBAC Roles (Document 2 §6)
SUPER_ADMIN, CLINIC_OWNER, DOCTOR, RECEPTIONIST, THERAPIST, NURSE, IPD_STAFF, ASSISTANT

## Key Constraints
- Transactions + locks + idempotency for double-booking/duplicate-payment protection
- Immutable financial/audit records (void/refund/correction/versioning, never delete)
- AI clinical output is draft-only — requires practitioner approval before becoming official
- NEVER fake integrations or expose secrets
- Provider integrations degrade gracefully when not configured (app must boot without keys)

## Plan Location
Full 26-phase plan: `.agents_tmp/PLAN.md`
Specs: `/workspace/vertopal.com_Klinic360_Document_*.json`

## Brand Palette
Brand teal: primary `#1f9482` (brand-500), dark `#167669` (brand-600). See `resources/css/app.css`.

## V1 Boundary
No pharmacy/medicine ordering/delivery. No commission on treatment revenue. Cashfree only for online consultation payments + SaaS subscription. Clinic-side payments: CASH/UPI/CARD/BANK_TRANSFER/CHEQUE/OTHER.
