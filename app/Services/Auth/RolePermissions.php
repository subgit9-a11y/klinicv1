<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Default permission grant per role (Document 2 §6).
 *
 * SUPER_ADMIN bypasses all checks (handled in {@see PermissionService}).
 * Per-user overrides (grants/revokes) are stored on the User model's
 * `permissions` JSON column and merged on top of these defaults.
 */
final class RolePermissions
{
    /** @var array<string, list<string>> */
    private static array $map = [
        'SUPER_ADMIN' => [], // bypassed entirely

        'CLINIC_OWNER' => [
            Permissions::PATIENTS_VIEW, Permissions::PATIENTS_CREATE, Permissions::PATIENTS_EDIT, Permissions::PATIENTS_DELETE, Permissions::PATIENTS_EXPORT,
            Permissions::APPOINTMENTS_VIEW, Permissions::APPOINTMENTS_CREATE, Permissions::APPOINTMENTS_EDIT, Permissions::APPOINTMENTS_CANCEL, Permissions::QUEUE_MANAGE,
            Permissions::CONSULTATIONS_VIEW, Permissions::CONSULTATIONS_CREATE, Permissions::CONSULTATIONS_EDIT, Permissions::VITALS_MANAGE,
            Permissions::PRESCRIPTIONS_VIEW, Permissions::PRESCRIPTIONS_CREATE, Permissions::PRESCRIPTIONS_EDIT,
            Permissions::TREATMENTS_VIEW, Permissions::TREATMENTS_CREATE, Permissions::TREATMENTS_EDIT,
            Permissions::IPD_VIEW, Permissions::IPD_ADMIT, Permissions::IPD_DISCHARGE, Permissions::IPD_NOTES,
            Permissions::BILLING_VIEW, Permissions::BILLING_CREATE, Permissions::BILLING_REFUND, Permissions::CASH_REGISTER_MANAGE,
            Permissions::AI_USE, Permissions::AI_PROMPTS_MANAGE,
            Permissions::DOCUMENTS_VIEW, Permissions::DOCUMENTS_UPLOAD, Permissions::DOCUMENTS_DELETE,
            Permissions::NOTIFICATIONS_MANAGE,
            Permissions::STAFF_MANAGE,
            Permissions::TREATMENTS_MANAGE,
            Permissions::IPD_CONFIGURE,
        ],

        'DOCTOR' => [
            Permissions::PATIENTS_VIEW, Permissions::PATIENTS_CREATE, Permissions::PATIENTS_EDIT,
            Permissions::APPOINTMENTS_VIEW, Permissions::APPOINTMENTS_CREATE, Permissions::APPOINTMENTS_EDIT, Permissions::APPOINTMENTS_CANCEL,
            Permissions::CONSULTATIONS_VIEW, Permissions::CONSULTATIONS_CREATE, Permissions::CONSULTATIONS_EDIT, Permissions::VITALS_MANAGE,
            Permissions::PRESCRIPTIONS_VIEW, Permissions::PRESCRIPTIONS_CREATE, Permissions::PRESCRIPTIONS_EDIT,
            Permissions::TREATMENTS_VIEW, Permissions::TREATMENTS_CREATE, Permissions::TREATMENTS_EDIT,
            Permissions::IPD_VIEW, Permissions::IPD_NOTES,
            Permissions::BILLING_VIEW,
            Permissions::AI_USE,
            Permissions::DOCUMENTS_VIEW, Permissions::DOCUMENTS_UPLOAD,
        ],

        'RECEPTIONIST' => [
            Permissions::PATIENTS_VIEW, Permissions::PATIENTS_CREATE, Permissions::PATIENTS_EDIT,
            Permissions::APPOINTMENTS_VIEW, Permissions::APPOINTMENTS_CREATE, Permissions::APPOINTMENTS_EDIT, Permissions::APPOINTMENTS_CANCEL, Permissions::QUEUE_MANAGE,
            Permissions::BILLING_VIEW, Permissions::BILLING_CREATE, Permissions::CASH_REGISTER_MANAGE,
            Permissions::DOCUMENTS_VIEW,
        ],

        'THERAPIST' => [
            Permissions::PATIENTS_VIEW,
            Permissions::APPOINTMENTS_VIEW,
            Permissions::TREATMENTS_VIEW, Permissions::TREATMENTS_CREATE, Permissions::TREATMENTS_EDIT,
            Permissions::DOCUMENTS_VIEW,
        ],

        'NURSE' => [
            Permissions::PATIENTS_VIEW,
            Permissions::APPOINTMENTS_VIEW,
            Permissions::CONSULTATIONS_VIEW,
            Permissions::VITALS_MANAGE,
            Permissions::IPD_VIEW, Permissions::IPD_NOTES,
            Permissions::DOCUMENTS_VIEW,
        ],

        'IPD_STAFF' => [
            Permissions::PATIENTS_VIEW,
            Permissions::APPOINTMENTS_VIEW,
            Permissions::IPD_VIEW, Permissions::IPD_ADMIT, Permissions::IPD_DISCHARGE, Permissions::IPD_NOTES,
            Permissions::BILLING_VIEW,
            Permissions::DOCUMENTS_VIEW,
        ],

        'ASSISTANT' => [
            Permissions::PATIENTS_VIEW, Permissions::PATIENTS_CREATE, Permissions::PATIENTS_EDIT,
            Permissions::APPOINTMENTS_VIEW, Permissions::APPOINTMENTS_CREATE,
            Permissions::BILLING_VIEW,
            Permissions::DOCUMENTS_VIEW,
        ],
    ];

    /**
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        return self::$map[$role] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function all(): array
    {
        return self::$map;
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return array_keys(self::$map);
    }
}
