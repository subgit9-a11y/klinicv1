<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Klinic 360 — Modern clinic management for Ayurveda, Siddha & Homeopathy</title>
    <meta name="description" content="Patient 360, appointments, EMR, billing, IPD, queue management and AI assistance for Ayurveda, Siddha & Homeopathy clinics. 14-day free trial.">
    @vite(['resources/css/app.css'])
    <style>
        .gradient-hero { background: linear-gradient(135deg,#0f766e 0%,#14b8a6 45%,#5eead4 100%); }
        .card-shadow { box-shadow: 0 10px 30px rgba(15,118,110,.12); }
        .badge { font-size: .7rem; font-weight: 700; letter-spacing: .06em; }
        details.faq summary::-webkit-details-marker{display:none}
    </style>
</head>
<body class="text-gray-800 antialiased">

<!-- NAV -->
<header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-gray-200">
    <div class="max-w-7xl mx-auto flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-lg bg-brand-600 text-white font-bold text-sm flex items-center justify-center">K360</span>
            <span class="font-bold text-gray-900">Klinic 360</span>
        </div>
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
            <a href="#features" class="hover:text-brand-600">Features</a>
            <a href="#how" class="hover:text-brand-600">How it works</a>
            <a href="#plans" class="hover:text-brand-600">Pricing</a>
            <a href="#faq" class="hover:text-brand-600">FAQ</a>
        </nav>
        <div class="flex items-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-brand-700 hover:underline">Dashboard →</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 hover:text-brand-700">Sign in</a>
                <a href="{{ route('signup') }}" class="px-4 py-2 rounded-md bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">Start free trial</a>
            @endauth
        </div>
    </div>
</header>

<!-- HERO -->
<section class="gradient-hero text-white">
    <div class="max-w-7xl mx-auto px-4 py-20 sm:py-28 text-center">
        <p class="badge bg-white/20 rounded-full px-3 py-1 inline-block mb-6">CLINIC OPERATING SYSTEM · FOR AYURVEDA / SIDDHA / HOMEOPATHY</p>
        <h1 class="text-4xl sm:text-6xl font-extrabold leading-tight max-w-4xl mx-auto">
            Your clinic, one system.<br class="hidden sm:block"> Everything — from patient to payment.
        </h1>
        <p class="mt-6 text-lg sm:text-xl text-teal-50 max-w-2xl mx-auto">
            Patient UID, 15-tab Patient 360, appointment queue, EMR consultations, treatments, IPD wards, billing, and AI assistance — running in your browser, no IT team needed.
        </p>
        <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('signup') }}" class="px-8 py-4 rounded-lg bg-white text-brand-700 font-bold text-lg shadow-lg hover:shadow-xl">
                Start 14-day free trial — no card
            </a>
            <a href="{{ route('online-booking.show') }}" class="px-8 py-4 rounded-lg border-2 border-white/60 font-semibold hover:bg-white/10">
                Try public booking demo
            </a>
        </div>
        <div class="mt-8 flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-teal-100">
            <span>✓ Free trial</span>
            <span>✓ Cancel anytime</span>
            <span>✓ Data stays in your clinic</span>
            <span>✓ Multi-language ready</span>
        </div>
    </div>
</section>

<!-- TRUST STRIP -->
<section class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
        <div><div class="text-3xl font-extrabold text-brand-700">15-tab</div><div class="text-sm text-gray-500">Patient 360 chart</div></div>
        <div><div class="text-3xl font-extrabold text-brand-700">8</div><div class="text-sm text-gray-500">RBAC roles</div></div>
        <div><div class="text-3xl font-extrabold text-brand-700">0%</div><div class="text-sm text-gray-500">commission on treatments</div></div>
        <div><div class="text-3xl font-extrabold text-brand-700">AI-assisted</div><div class="text-sm text-gray-500">draft-only, doctor-approved</div></div>
    </div>
</section>

<!-- FEATURES -->
<section id="features" class="py-16 sm:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <p class="text-sm font-bold text-brand-600 text-center uppercase tracking-widest">Everything included</p>
        <h2 class="mt-2 text-3xl sm:text-4xl font-extrabold text-center text-gray-900">One login. The entire clinic.</h2>
        <p class="mt-4 text-center text-gray-600 max-w-2xl mx-auto">From walk-in queue to discharge summary — no spreadsheet juggling, no paper registers.</p>

        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl p-6 card-shadow">
                <div class="text-2xl">🧬</div>
                <h3 class="mt-3 font-bold text-gray-900">Patient UID & Patient 360</h3>
                <p class="mt-2 text-sm text-gray-600">Permanent K360-P-########## UID, 15-tab chart (vitals, history, investigations, consents, documents, AI summaries). Search by phone/name/ID.</p>
            </div>
            <div class="bg-white rounded-xl p-6 card-shadow">
                <div class="text-2xl">📅</div>
                <h3 class="mt-3 font-bold text-gray-900">Appointments & Queue</h3>
                <p class="mt-2 text-sm text-gray-600">Walk-ins + scheduled, doctor-wise token queue (WAITING→CALLED→DONE), real-time slots for your website's public booking page.</p>
            </div>
            <div class="bg-white rounded-xl p-6 card-shadow">
                <div class="text-2xl">🩺</div>
                <h3 class="mt-3 font-bold text-gray-900">EMR Consultations</h3>
                <p class="mt-2 text-sm text-gray-600">SOAP notes, vitals, diagnoses, prescriptions for Ayurveda/Siddha/Homeopathy. Amend-with-reason audit trail on every clinical edit.</p>
            </div>
            <div class="bg-white rounded-xl p-6 card-shadow">
                <div class="text-2xl">🛏️</div>
                <h3 class="mt-3 font-bold text-gray-900">IPD Wards & Treatments</h3>
                <p class="mt-2 text-sm text-gray-600">Ward→room→bed occupancy, admission→daily-notes→discharge, treatment packages (Panchakarma etc.) with session tracking.</p>
            </div>
            <div class="bg-white rounded-xl p-6 card-shadow">
                <div class="text-2xl">💳</div>
                <h3 class="mt-3 font-bold text-gray-900">Billing & Cash Register</h3>
                <p class="mt-2 text-sm text-gray-600">Immutable invoices (DRAFT→ISSUED→PAID/REFUNDED), Cashfree online payments, cash register with variance tracking, payment receipts as PDF.</p>
            </div>
            <div class="bg-white rounded-xl p-6 card-shadow">
                <div class="text-2xl">🤖</div>
                <h3 class="mt-3 font-bold text-gray-900">AI Assistance (draft-only)</h3>
                <p class="mt-2 text-sm text-gray-600">AI Scribe, patient/lab/document/treatment summaries, follow-up & clinical assistant — always draft until the practitioner approves.</p>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section id="how" class="py-16 bg-white">
    <div class="max-w-6xl mx-auto px-4">
        <p class="text-sm font-bold text-brand-600 text-center uppercase tracking-widest">Live in under 10 minutes</p>
        <h2 class="mt-2 text-3xl sm:text-4xl font-extrabold text-center text-gray-900">How it works</h2>
        <div class="mt-12 grid sm:grid-cols-4 gap-8 text-center">
            <div><div class="w-12 h-12 mx-auto rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold">1</div><h4 class="mt-4 font-bold">Signup clinic</h4><p class="mt-1 text-sm text-gray-600">Pick plan, create owner — trial activates instantly.</p></div>
            <div><div class="w-12 h-12 mx-auto rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold">2</div><h4 class="mt-4 font-bold">Add doctors & wards</h4><p class="mt-1 text-sm text-gray-600">Guided wizard, skip any step — you can edit later.</p></div>
            <div><div class="w-12 h-12 mx-auto rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold">3</div><h4 class="mt-4 font-bold">Register patients</h4><p class="mt-1 text-sm text-gray-600">Queue walk-ins, book online slots on your website.</p></div>
            <div><div class="w-12 h-12 mx-auto rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold">4</div><h4 class="mt-4 font-bold">Get paid</h4><p class="mt-1 text-sm text-gray-600">Cashfree online + cash/UPI/card at counter, receipts as PDF.</p></div>
        </div>
    </div>
</section>

<!-- SECURITY / GOVERNANCE -->
<section class="py-16 bg-gray-900 text-white">
    <div class="max-w-6xl mx-auto px-4 grid sm:grid-cols-2 gap-10 items-center">
        <div>
            <p class="text-sm font-bold text-brand-400 uppercase tracking-widest">Enterprise-grade by design</p>
            <h2 class="mt-2 text-3xl font-extrabold">Multi-tenant RBAC, audit trail, AI governance.</h2>
            <ul class="mt-6 space-y-3 text-gray-300 text-sm">
                <li>✓ 8 RBAC roles — Super Admin down to Assistant; every action permission-checked</li>
                <li>✓ Tenant-isolation — one clinic cannot read another clinic's data, ever</li>
                <li>✓ Immutable financial records — void/refund with reason, never delete; double-booking prevented by row locks</li>
                <li>✓ AI draft-only — every AI output must be approved by the practitioner before it's clinical record</li>
                <li>✓ Full audit log of logins, payments, feature flags, clinical amendments</li>
                <li>✓ Health probes (<code class="text-brand-300">/health</code>, <code class="text-brand-300">/health/ready</code>) for uptime monitors</li>
            </ul>
        </div>
        <div class="bg-gray-800 rounded-2xl p-6 text-sm">
            <div class="font-bold mb-3 text-brand-300">Quick self-check (public endpoints)</div>
            <ul class="space-y-2 text-gray-400">
                <li>• <code>/health</code> — app liveness</li>
                <li>• <code>/health/ready</code> — DB + queue tables reachable</li>
                <li>• <code>/book</code> — real-time public slot picker</li>
                <li>• <code>/book/status</code> — guest booking lookup by ref + phone</li>
                <li>• <code>/api/v1/…</code> — Sanctum-token REST for integrations</li>
                <li>• Super Admin manages clinics, plans, RBAC, AI prompts, integrations, templates</li>
            </ul>
        </div>
    </div>
</section>

<!-- PRICING -->
<section id="plans" class="py-16 bg-gray-50">
    <div class="max-w-6xl mx-auto px-4">
        <p class="text-sm font-bold text-brand-600 text-center uppercase tracking-widest">Simple pricing, no hidden fees</p>
        <h2 class="mt-2 text-3xl sm:text-4xl font-extrabold text-center text-gray-900">Pick the plan that fits your clinic</h2>
        <p class="mt-4 text-center text-gray-600">14-day free trial on both plans. Cancel any time from Billing.</p>
        <div class="mt-12 grid sm:grid-cols-2 gap-8 max-w-4xl mx-auto">
            <div class="bg-white rounded-2xl p-8 card-shadow border border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">Solo Doctor</h3>
                <p class="mt-2 text-sm text-gray-500">Perfect for a single practitioner.</p>
                <div class="mt-5"><span class="text-4xl font-extrabold text-gray-900">₹999</span><span class="text-gray-500">/month</span></div>
                <ul class="mt-6 space-y-2 text-sm text-gray-700">
                    <li>✓ 1 doctor + receptionist</li>
                    <li>✓ Unlimited patients & appointments</li>
                    <li>✓ IPD, treatments, billing</li>
                    <li>✓ AI assistance (draft-only)</li>
                    <li>✓ Email + WhatsApp notifications</li>
                </ul>
                <a href="{{ route('signup') }}" class="mt-6 block text-center rounded-lg bg-gray-900 text-white font-semibold py-3 hover:bg-gray-800">Start trial — ₹999</a>
            </div>
            <div class="bg-brand-700 text-white rounded-2xl p-8 card-shadow">
                <h3 class="text-lg font-bold">Small Clinic</h3>
                <p class="mt-2 text-sm text-teal-100">Up to 10 staff — OPD + IPD + treatments.</p>
                <div class="mt-5"><span class="text-4xl font-extrabold">₹1,999</span><span class="text-teal-200">/month</span></div>
                <ul class="mt-6 space-y-2 text-sm text-teal-100">
                    <li>✓ Up to 10 users + receptionist</li>
                    <li>✓ Everything in Solo + IPD wards</li>
                    <li>✓ Treatment packages & reports</li>
                    <li>✓ AI Scribe + lab summaries</li>
                    <li>✓ Priority support</li>
                </ul>
                <a href="{{ route('signup') }}" class="mt-6 block text-center rounded-lg bg-white text-brand-700 font-semibold py-3 hover:bg-teal-50">Start trial — ₹1,999</a>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-16 bg-white">
    <div class="max-w-3xl mx-auto px-4">
        <p class="text-sm font-bold text-brand-600 text-center uppercase tracking-widest">Questions</p>
        <h2 class="mt-2 text-3xl font-extrabold text-center text-gray-900">Frequently asked</h2>
        <div class="mt-10 space-y-3 text-sm">
            <details class="faq border border-gray-200 rounded-lg p-4">
                <summary class="font-semibold cursor-pointer text-gray-900">Do I need to install anything on a computer?</summary>
                <p class="mt-2 text-gray-600">No. It's a website — run it on any browser (desktop, tablet, phone). Your doctors download the app to their phones via PWA.</p>
            </details>
            <details class="faq border border-gray-200 rounded-lg p-4">
                <summary class="font-semibold cursor-pointer text-gray-900">Can I keep my paper register data?</summary>
                <p class="mt-2 text-gray-600">Yes. Import CSVs of patients into Patient 360 via the API, or let staff add patients gradually as they come in.</p>
            </details>
            <details class="faq border border-gray-200 rounded-lg p-4">
                <summary class="font-semibold cursor-pointer text-gray-900">Is my patient data secure?</summary>
                <p class="mt-2 text-gray-600">Yes. Multi-tenant isolation, row-level audit logs, encrypted integrations, and a full pentest sweep (XSS/SQLi/IDOR tests). AI is always draft-only until the doctor approves.</p>
            </details>
            <details class="faq border border-gray-200 rounded-lg p-4">
                <summary class="font-semibold cursor-pointer text-gray-900">How do online payments work?</summary>
                <p class="mt-2 text-gray-600">Cashfree hosts the checkout; webhooks confirm payment server-side before the appointment is confirmed. Counter-side also supports cash/UPI/card/Cheque.</p>
            </details>
            <details class="faq border border-gray-200 rounded-lg p-4">
                <summary class="font-semibold cursor-pointer text-gray-900">What happens after the 14-day trial?</summary>
                <p class="mt-2 text-gray-600">Move to any paid plan from Billing. A grace period runs before the clinic is suspended — no data loss.</p>
            </details>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="bg-gray-900 text-gray-400">
    <div class="max-w-7xl mx-auto px-4 py-12 grid sm:grid-cols-4 gap-8 text-sm">
        <div>
            <div class="font-bold text-white">Klinic 360</div>
            <p class="mt-2">Clinic operating system for Ayurveda, Siddha & Homeopathy.</p>
        </div>
        <div>
            <div class="font-semibold text-white mb-2">Product</div>
            <ul class="space-y-1">
                <li><a href="#features" class="hover:text-white">Features</a></li>
                <li><a href="#how" class="hover:text-white">How it works</a></li>
                <li><a href="#plans" class="hover:text-white">Pricing</a></li>
            </ul>
        </div>
        <div>
            <div class="font-semibold text-white mb-2">Company</div>
            <ul class="space-y-1">
                <li><a href="#faq" class="hover:text-white">FAQ</a></li>
                <li><a href="{{ route('login') }}" class="hover:text-white">Sign in</a></li>
                <li><a href="{{ route('signup') }}" class="hover:text-white">Start trial</a></li>
            </ul>
        </div>
        <div>
            <div class="font-semibold text-white mb-2">Compliance</div>
            <ul class="space-y-1">
                <li>Draft-only AI</li>
                <li>Immutable audit</li>
                <li>Tenant isolation</li>
            </ul>
        </div>
    </div>
    <div class="border-t border-gray-800 py-6 text-center text-xs">
        © {{ date('Y') }} Klinic 360. All rights reserved.
    </div>
</footer>
</body>
</html>
