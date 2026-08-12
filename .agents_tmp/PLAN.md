# 1. OBJECTIVE

Build **Klinic 360** from scratch — a production-grade, AI-powered multi-tenant B2B SaaS clinic operating system for small Ayurveda, Siddha, and Homeopathy practices. The product provides a permanent Patient UID, Patient 360, a unified appointment/queue engine, EMR for three traditional-medicine systems, treatment/IPD management, a core billing/payment engine, AI assistance (Gemini), notifications, documents, reports, and a Super Admin control plane. It is a modular Laravel monolith deployed on cPanel, with two plans (Solo Doctor ₹999/mo, Small Clinic ₹1,999/mo).

Source specifications:
- `/workspace/vertopal.com_Klinic360_Document_1_Product_Functional_Specification.json` — defines WHAT the product must do (30 sections).
- `/workspace/vertopal.com_Klinic360_Document_2_Technical_Implementation_Master_Prompt.json` — defines HOW to engineer, integrate, test, and deploy (35 sections, 26 phases).

# 2. CONTEXT SUMMARY

**Starting state:** `/workspace/project` is empty (no existing code). Per Document 2 §35, begin by initializing a fresh Laravel project.

**Tech stack (mandated by Document 2 §2–3):**
- Backend: PHP 8.3+ / Laravel 12+ (latest compatible stable)
- Frontend: Blade + Livewire 3+ + Alpine.js + Tailwind CSS + Vite
- Database: MySQL 8+ (MariaDB-compatible)
- Auth: Laravel sessions, email verification, password reset, optional 2FA
- Authorization: Policies/Gates + RBAC
- API: REST `/api/v1` + Sanctum (token auth where needed)
- Queue: database initially, Redis-ready
- Scheduler: Laravel Scheduler; Cron `* * * * * php artisan schedule:run`
- Deploy: cPanel (app outside web root, docroot → `/public`)

**Architecture:** Modular Laravel monolith. No React/Next.js/Node/K8s/Docker/microservices in V1. Business logic in Services, integrations behind interfaces, validation in Form Requests, authorization in Policies, presentation in Blade/Livewire.

**Multi-tenancy:** `tenants` table; every tenant-owned table has `tenant_id`; isolation enforced via middleware + scoped queries + services + policies + route/model binding. `tenant_id` never trusted from browser.

**RBAC roles:** SUPER_ADMIN, CLINIC_OWNER, DOCTOR, RECEPTIONIST, THERAPIST, NURSE, IPD_STAFF, ASSISTANT, with granular permissions.

**External providers (real APIs only, Document 2 §10):** Gemini (AI), Cashfree (payments + SaaS subscription), Google Meet (video), Meta WhatsApp Cloud API, MSG91 (SMS), Resend/SMTP (email), S3/S3-compatible (private documents). `.env`-driven credentials; providers built behind interfaces and gracefully degrade when not configured so the app runs without keys.

**Database scale:** ~60 tables (Document 2 §12) across tenancy, plans/subscriptions, patients, appointments/queue, EMR (general + Ayurveda/Siddha/Homeopathy), prescriptions/vitals, treatments/therapists/rooms/packages, IPD, billing/payments, notifications, documents, AI governance, audit logs, settings, feature flags.

**Plans:** Solo Doctor ₹999/mo, Small Clinic ₹1,999/mo — database-driven via PlanService/FeatureService/LimitService/PermissionService (no hard-coded plan checks).

**V1 boundary:** No pharmacy/medicine ordering/delivery. No commission on clinic treatment revenue. Clinic-side payments (treatment/walk-in/IPD) collected at clinic via CASH/UPI/CARD/BANK_TRANSFER/CHEQUE/OTHER. Cashfree only for online consultation payments and SaaS subscription.

**Key constraints:** Transactions + locks + idempotency for double-booking/duplicate-payment protection; immutable financial/audit records (void/refund/correction/versioning, never delete); AI clinical output is draft-only requiring practitioner approval; never fake integrations or expose secrets.

# 3. APPROACH OVERVIEW

Execute the **26 implementation phases** defined in Document 2 §30 in their specified order, applying the **development loop** from §29 to every phase: plan → migrations/models → services → authorization → UI → validation → tests → migrate/seed → run tests → fix → security review → commit → document → next phase.

**Why this approach:** The specification is prescriptive about both ordering and per-phase output; following the documented phase order respects dependency chains (e.g., multi-tenancy before any tenant-owned module; RBAC before Super Admin; Patient UID before Patient 360; EMR before prescriptions; core billing before Cashfree). The development loop guarantees each phase is independently testable and committed.

**Structural decisions:**
- **Provider integrations behind interfaces** with config-driven resolution and graceful "not configured" fallbacks — satisfies "never fake success" while letting the app boot and be tested without live credentials; real calls activate once keys are supplied.
- **Modular monolith** with the `app/` structure from Document 2 §4 (Services, Integrations, Contracts, Policies, Livewire, etc.).
- **Git from day one** with `main`/`develop`/`feature/*`/`fix/*` branches; commit each meaningful phase.
- **Open questions (pending user confirmation):** (1) whether to scope the first build pass to foundational phases 1–16 (option B) or plan the complete 26-phase build (option A); (2) confirmation that env-driven graceful-fallback provider wiring is acceptable.

# 4. IMPLEMENTATION STEPS

Each step applies the development loop (plan → migrations/models → services → authorization → UI → validation → tests → seed → run tests → fix → security review → commit → document).

### Phase 1 — Laravel Foundation
- **Goal:** Bootable Laravel app with the mandated stack.
- **Method:** `composer create-project laravel/laravel`, add Livewire 3, Alpine, Tailwind, Vite; configure `app/` directory skeleton (Console, Events, Exceptions, Http/{Controllers,Middleware,Requests,Resources}, Jobs, Livewire, Models, Notifications, Policies, Providers, Services, Integrations, Contracts). Add base layouts/components (tables, filters, forms, modals, drawers, calendars, timelines, patient header, status badges).
- **Reference:** Document 2 §2–4, §23.

### Phase 2 — Database Design
- **Goal:** Full schema for ~60 tables with FKs, unique constraints, composite indexes.
- **Method:** Generate migrations/models for tenancy, plans/subscriptions, patients/identifiers/consents, appointments/tokens/history/availability, consultations/clinical_notes/vitals/diagnoses/prescriptions/items, treatments (services/bookings/plans/packages/sessions), therapists/availability/rooms/availability, IPD (admissions/wards/rooms/beds/daily_notes/vitals/nursing/discharge), billing (invoices/items/payments/orders/webhooks/refunds/expenses/cash_registers/entries), teleconsultations, documents, followups, investigations/results, notifications/templates/deliveries, AI (features/models/prompts/versions/credentials/requests), integration_accounts, feature_flags, custom_fields/values, audit_logs, system_settings, tenant_settings. Index on tenant_id, patient UID, phone, appointment date, doctor_id, status, invoice number, payment number, Cashfree order ID, meeting ID, notification event ID.
- **Reference:** Document 2 §12–13.

### Phase 3 — Authentication
- **Goal:** Secure session auth with email verification, password reset, optional 2FA.
- **Method:** Laravel auth scaffolding (Blade/Livewire), email verification middleware, rate-limited reset, 2FA toggle.
- **Reference:** Document 2 §2 (auth), §26.

### Phase 4 — Multi-Tenancy
- **Goal:** Tenant isolation enforced everywhere.
- **Method:** `tenants` table, `tenant_id` on all tenant-owned tables, tenant-resolution middleware, global scopes on tenant models, TenantService, scoped queries + route/model binding. Never trust client-supplied `tenant_id`.
- **Reference:** Document 2 §5, §13.

### Phase 5 — RBAC
- **Goal:** Role/permission system for the 8 roles with granular permissions.
- **Method:** Roles (SUPER_ADMIN, CLINIC_OWNER, DOCTOR, RECEPTIONIST, THERAPIST, NURSE, IPD_STAFF, ASSISTANT), permission tables, Gates/Policies for patients/appointments/consultations/prescriptions/treatments/IPD/billing/AI/documents. PermissionService.
- **Reference:** Document 2 §6, §7.

### Phase 6 — Super Admin
- **Goal:** Super Admin control plane for tenants, users, plans, pricing, features, providers, integrations, notifications, clinical config, reports, audit.
- **Method:** Super Admin dashboard (non-tenant scope), tenant/user management, feature flags, audit log viewer.
- **Reference:** Document 1 §26; Document 2 §6.

### Phase 7 — Plans & Subscriptions
- **Goal:** Database-driven Solo Doctor (₹999) / Small Clinic (₹1,999) plans.
- **Method:** plans, plan_features, subscriptions, subscription_events; PlanService, FeatureService, LimitService, PermissionService (no hard-coded plan logic). SaaS subscription billing via Cashfree (Phase 17 wires payment).
- **Reference:** Document 1 §3–4; Document 2 §7.

### Phase 8 — Patient UID
- **Goal:** Permanent K360-P-0000012487-style UIDs independent of phone/email/ABHA.
- **Method:** patient_identifiers table, sequential/atomic UID generation inside transactions, uniqueness enforced by constraint + retry.
- **Reference:** Document 1 §5; Document 2 §14.

### Phase 9 — Patient 360
- **Goal:** Unified patient record with Overview, Timeline, Appointments, OPD, Prescriptions, Treatments, IPD, Investigations, Documents, Follow-ups, Billing, Payments, AI Summary, Consent, ABHA.
- **Method:** PatientService, patient header component, timeline aggregation across modules, patient_consents.
- **Reference:** Document 1 §5; Document 2 §8.

### Phase 10 — Appointments
- **Goal:** Unified engine for WALK_IN, IN_PERSON, ONLINE, FOLLOW_UP, TREATMENT, IPD_REVIEW with collision prevention.
- **Method:** appointments, appointment_status_history, appointment_tokens, doctor_availability; AppointmentService; slot collision checks via constraints + locks.
- **Reference:** Document 1 §6; Document 2 §8, §14.

### Phase 11 — Queue
- **Goal:** Walk-in token/queue flow (UID → token → queue → consultation → prescription → invoice → payment → receipt → follow-up).
- **Method:** appointment_tokens, QueueService, queue board UI, arrival/assignment events.
- **Reference:** Document 1 §6, §29; Document 2 §8.

### Phase 12 — EMR
- **Goal:** General EMR (chief complaint, history, examination, vitals, assessment, diagnosis, plan, advice, follow-up) + system-specific: Ayurveda (Prakriti/Vikriti/Dosha/Srotas/Agni/Ama/Samprapti/Rogi&Roga Pareeksha…), Siddha (Mukkutram/Udal Thathukkal/Naadi/Neerkuri/Neikuri/Envagai Thervu…), Homeopathy (symptoms/modalities/constitution/repertory/remedy/potency/dose…).
- **Method:** consultations, clinical_notes, vitals, diagnoses; ConsultationService; system-specific structured forms; doctor_availability reuse.
- **Reference:** Document 1 §8–11; Document 2 §8.

### Phase 13 — Prescriptions & Vitals
- **Goal:** Vitals (BP, pulse, temp, RR, SpO2, height, weight, BMI, pain, custom) + prescriptions (medicine/remedy, form, strength, dose, frequency, duration, route, quantity, instructions, timing, Anupana, external application).
- **Method:** prescriptions, prescription_items, vitals; PrescriptionService; prescription PDF generation (Phase 21 PDF infra).
- **Reference:** Document 1 §12; Document 2 §8.

### Phase 14 — Treatment
- **Goal:** Treatment catalogue/categories/services/duration/price/therapist/room/availability/booking/calendar/plans/packages/sessions/history; packages (e.g., Panchakarma) with purchased/completed/remaining/expiry and entitlement consumption.
- **Method:** treatment_services/bookings/plans/plan_items/packages/package_items/sessions, therapists, therapist_availability, treatment_rooms, room_availability; TreatmentService; booking flow patient → treatment → date/time → therapist → room → booking → PAY AT CLINIC; therapist/room collision prevention.
- **Reference:** Document 1 §13–15; Document 2 §8, §14.

### Phase 15 — IPD
- **Goal:** Lightweight IPD: OPD → admission → ward/room/bed → daily notes → vitals → treatment → investigations → billing → discharge; bed states AVAILABLE/RESERVED/OCCUPIED/CLEANING/MAINTENANCE/BLOCKED.
- **Method:** ipd_admissions/wards/rooms/beds/daily_notes/vitals/nursing_notes/discharge_summaries; IPDService; bed allocation under transaction+lock; discharge releases bed and triggers Phase 16 billing.
- **Reference:** Document 1 §16, §20; Document 2 §8, §14.

### Phase 16 — Billing
- **Goal:** Core transaction engine across appointments, consultation, treatment, IPD, packages; invoices/items, partial payments, advances, outstanding balances, receipts, refunds, daily collections, cash register, expenses; immutable records.
- **Method:** invoices, invoice_items, payments, refunds, expenses, cash_registers, cash_register_entries; BillingService, PaymentService, CashRegisterService; void/refund/correction/versioning (no deletes); clinic-side payment methods CASH/UPI/CARD/BANK_TRANSFER/CHEQUE/OTHER.
- **Reference:** Document 1 §17–20; Document 2 §8, §27.

### Phase 17 — Cashfree Integration
- **Goal:** Real Cashfree for online consultation payments + SaaS subscription.
- **Method:** PaymentGatewayInterface + CashfreePaymentProvider; order creation, lookup, verification, refund, webhook signature verification; idempotent webhook processing via stored event IDs; payment_orders/payment_webhooks; never trust frontend success; server-side verification.
- **Reference:** Document 1 §7, §18; Document 2 §9, §10, §15, §14.

### Phase 18 — Google Meet Integration
- **Goal:** Real Meet spaces for online consultations.
- **Method:** VideoProviderInterface + GoogleMeetProvider; secure Google OAuth; create real Meet spaces via current Google API; store meeting IDs/URLs on appointments/teleconsultations; no fake links.
- **Reference:** Document 1 §7; Document 2 §9, §10, §16.

### Phase 19 — Notifications
- **Goal:** Central engine (in-app, WhatsApp, SMS, email) for registration, appointment confirm/reminders, online payment, online consultation, treatment booking/reminders, follow-up, documents, IPD admission/discharge, arrival, assignment.
- **Method:** Events + Listeners + queued Jobs; notifications, notification_templates (database-driven, versionable), notification_deliveries; NotificationService; WhatsAppProviderInterface (Meta Cloud API), SmsProviderInterface (MSG91), EmailProviderInterface (Resend/SMTP); delivery status + retries.
- **Reference:** Document 1 §21; Document 2 §9, §10, §20.

### Phase 20 — Gemini AI
- **Goal:** AI Scribe, Patient/Timeline/Document/Treatment/IPD summaries, Lab Report Extraction, Follow-up Assistant, Ayurveda/Siddha/Homeopathy Assistants, Clinic Assistant.
- **Method:** AIProviderInterface + GeminiProvider + AIManager + AIContextBuilder; server-side API key; text/structured output/document+image analysis; ai_features/models/prompts/prompt_versions/provider_credentials/requests (versioned prompts, token/cost/duration/status/errors, minimal patient logging); draft-only clinical output with mandatory practitioner approval; Clinic Assistant via controlled tools (no unrestricted SQL).
- **Reference:** Document 1 §23–24; Document 2 §9, §10, §17–19.

### Phase 21 — Documents & PDF
- **Goal:** Private S3/S3-compatible document storage with authorization + signed URLs/authorized streaming + access audit; PDF generation for prescriptions, invoices, receipts, treatment plans, discharge summaries, reports.
- **Method:** documents; DocumentService; StorageProviderInterface (S3/S3-compatible); OCRProviderInterface; signed URLs + audit logging; Laravel-compatible PDF library for templated PDFs.
- **Reference:** Document 1 §22; Document 2 §9, §21.

### Phase 22 — Reports
- **Goal:** Clinical (patients, consultations, follow-ups), Appointments (walk-ins, online, completed, cancelled, no-show), Treatment (bookings, sessions, utilization), IPD (admissions, discharges, occupancy), Finance (collections, outstanding, refunds, treatment/IPD collections, expenses, cash register).
- **Method:** ReportService; configurable/filters; Super Admin + clinic-scoped views.
- **Reference:** Document 1 §28; Document 2 §8.

### Phase 23 — Super Admin Configuration
- **Goal:** Database-driven business configuration: plans, appointment types/durations, treatments/prices, therapist services, rooms, beds, notification templates, AI prompts/models/limits, invoice/receipt/prescription/discharge templates, feature flags.
- **Method:** Super Admin config UI over system_settings/tenant_settings/feature_flags/notification_templates/ai_prompts/ai_models; no hard-coded business rules.
- **Reference:** Document 1 §27; Document 2 §7.

### Phase 24 — Security Hardening
- **Goal:** All mandated controls active.
- **Method:** HTTPS, CSRF, XSS protection, SQL injection prevention, password hashing, rate limiting, RBAC/Policies, tenant isolation, secure sessions, encrypted credentials, private documents, backups, audit logs; `APP_DEBUG=false` in prod. Audit auth, patient access, clinical changes, prescriptions, treatment, IPD, invoices, payments, refunds, AI requests, document access, permission changes, Super Admin config.
- **Reference:** Document 2 §26–27.

### Phase 25 — Testing
- **Goal:** PHPUnit/Pest coverage of critical paths.
- **Method:** Tests for tenant isolation, UID uniqueness, duplicate patients, appointment collisions, walk-in queue, online booking, Cashfree verification, duplicate webhooks, refunds, treatment room/therapist collisions, IPD bed allocation, discharge billing, partial payments, AI limits, authorization, document access, API security.
- **Reference:** Document 2 §28.

### Phase 26 — cPanel Deployment & Docs
- **Goal:** Deployable to cPanel with full documentation.
- **Method:** App outside web root, docroot → `/public`; Composer/MySQL/SSH/Git/SSL/PHP extensions; database queue + future Redis strategy; cron entry; README.md, INSTALLATION.md, CPANEL_DEPLOYMENT.md, ENVIRONMENT.md, SECURITY.md, API.md, ADMIN_GUIDE.md; demo seeders (Super Admin, Solo Doctor tenant, Small Clinic tenant, doctors, receptionist, therapist, nurse, patients, treatments, rooms, IPD beds, appointments, invoices, payments — synthetic only).
- **Reference:** Document 2 §24–25, §31–33.

### Cross-Cutting (applied throughout)
- **API layer:** Version `/api/v1` with Form Requests, API Resources, Sanctum auth, tenant context, policies, rate limiting, validation — patients, appointments, consultations, treatments, IPD, billing, payments, teleconsultations, webhooks. (Document 2 §22)
- **Scheduler jobs:** appointment reminders, treatment reminders, follow-ups, subscription checks, notification retries, AI jobs, cleanup, payment reconciliation. (Document 2 §25)
- **Git:** `main`/`develop`/`feature/*`/`fix/*`; commit each phase. (Document 2 §31)
- **Documentation:** maintained alongside each phase. (Document 2 §32)

# 5. TESTING AND VALIDATION

**Per-phase loop validation:** Every phase must pass: migrations migrate cleanly, seeders run, the phase's PHPUnit/Pest tests pass, a manual smoke of the UI/API path works, and a security review (tenant isolation + authorization + no secret exposure) is recorded before commit.

**Definition of Done (Document 1 §30, Document 2 §34):** All core modules and integrations work end-to-end, tests pass, tenant isolation verified, security controls active, cPanel deployment documented and tested. Concretely the following must be demonstrable end-to-end:
- Authentication + tenant isolation + RBAC enforced.
- Patient UID generation unique; Patient 360 renders across modules.
- Walk-in flow: UID → token → queue → consultation → prescription → invoice → cash/UPI → receipt → follow-up.
- Online consultation flow: public booking → slot → Cashfree (server-verified) → invoice → Google Meet → consultation → prescription.
- Treatment flow: booking → therapist/room → session → invoice → clinic payment.
- IPD flow: admission → bed → notes/vitals/treatment → billing → discharge → payment → summary.
- EMR for general + Ayurveda + Siddha + Homeopathy; prescriptions + vitals.
- Billing: invoices, partial payments, advances, outstanding, receipts, refunds, cash register, expenses — immutable.
- AI: Gemini features produce draft output requiring practitioner approval; no autonomous clinical actions.
- Notifications delivered across in-app/WhatsApp/SMS/email; documents private + audited; reports render across all categories.
- Super Admin config is database-driven; audit logs capture all mandated events.

**Test suite (Document 2 §28):** tenant isolation, UID uniqueness, duplicate patients, appointment collisions, walk-in queue, online booking, Cashfree verification, duplicate webhooks, refunds, treatment room/therapist collisions, IPD bed allocation, discharge billing, partial payments, AI limits, authorization, document access, API security.

**Integration validation:** Without live credentials, provider calls degrade gracefully and log "not configured"; with credentials supplied, Cashfree order/verify/refund/webhook, Google Meet space creation, Gemini text/structured/multimodal, WhatsApp/SMS/email send must hit real APIs and never return fake success.
