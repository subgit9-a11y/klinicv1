<?php

declare(strict_types=1);

/**
 * Klinic 360 application configuration.
 *
 * Centralizes operational settings (audit retention, notification retry
 * limits, AI defaults, cache TTLs) that were previously hard-coded or
 * scattered across services. Environment-driven via the KLINIC_* prefix.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Audit & Token Retention
    |--------------------------------------------------------------------------
    | Records older than this window are purged by the klinic:cleanup
    | command. Set to 0 to keep records indefinitely.
    */
    'audit_retention_days' => (int) env('KLINIC_AUDIT_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Notification Retry
    |--------------------------------------------------------------------------
    | Max delivery attempts for a notification before it is marked FAILED.
    | RetryNotifications dispatches PENDING/FAILED deliveries up to this
    | count. The backoff (seconds) is applied between attempts.
    */
    'notification_max_attempts' => (int) env('KLINIC_NOTIFICATION_MAX_ATTEMPTS', 3),
    'notification_retry_backoff' => (int) env('KLINIC_NOTIFICATION_RETRY_BACKOFF', 300),

    /*
    |--------------------------------------------------------------------------
    | AI Defaults
    |--------------------------------------------------------------------------
    | Fallback values when no AI model is configured at the tenant or
    | platform level. Providers may still override per-request.
    */
    'ai' => [
        'default_provider' => env('KLINIC_AI_DEFAULT_PROVIDER', 'gemini'),
        'default_model' => env('KLINIC_AI_DEFAULT_MODEL', 'gemini-2.0-flash'),
        'timeout_seconds' => (int) env('KLINIC_AI_TIMEOUT', 30),
        'max_tokens' => (int) env('KLINIC_AI_MAX_TOKENS', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTLs (seconds)
    |--------------------------------------------------------------------------
    | How long permission, feature-flag, and plan lookups are cached before
    | re-querying the database. Short TTLs keep multi-tenant data fresh
    | while dramatically reducing DB hits on hot request paths.
    */
    'cache_ttl' => [
        'permissions' => (int) env('KLINIC_CACHE_PERMISSIONS_TTL', 300),
        'feature_flags' => (int) env('KLINIC_CACHE_FEATURE_FLAGS_TTL', 60),
        'plans' => (int) env('KLINIC_CACHE_PLANS_TTL', 600),
        'tenant_config' => (int) env('KLINIC_CACHE_TENANT_TTL', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    | Default per-page count for API collection endpoints unless a request
    | explicitly requests more (capped by max_per_page).
    */
    'pagination' => [
        'default_per_page' => (int) env('KLINIC_PAGINATION_PER_PAGE', 20),
        'max_per_page' => (int) env('KLINIC_PAGINATION_MAX', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription & Billing
    |--------------------------------------------------------------------------
    | Grace period (days) before a past-due subscription loses feature
    | access. The CheckSubscriptions command honors this window.
    */
    'subscription_grace_days' => (int) env('KLINIC_SUBSCRIPTION_GRACE_DAYS', 7),

];
