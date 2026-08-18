<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Catalog of all granular RBAC permissions, grouped by module (Document 2 §6).
 *
 * Each constant is a dotted permission key of the form `<module>.<action>`.
 * Roles map to a default set of these via {@see RolePermissions}.
 * Super Admin bypasses all checks.
 */
final class Permissions
{
    // Patients
    public const PATIENTS_VIEW = 'patients.view';

    public const PATIENTS_CREATE = 'patients.create';

    public const PATIENTS_EDIT = 'patients.edit';

    public const PATIENTS_DELETE = 'patients.delete';

    public const PATIENTS_EXPORT = 'patients.export';

    // Appointments & Queue
    public const APPOINTMENTS_VIEW = 'appointments.view';

    public const APPOINTMENTS_CREATE = 'appointments.create';

    public const APPOINTMENTS_EDIT = 'appointments.edit';

    public const APPOINTMENTS_CANCEL = 'appointments.cancel';

    public const QUEUE_MANAGE = 'queue.manage';

    // Consultations / EMR
    public const CONSULTATIONS_VIEW = 'consultations.view';

    public const CONSULTATIONS_CREATE = 'consultations.create';

    public const CONSULTATIONS_EDIT = 'consultations.edit';

    public const VITALS_MANAGE = 'vitals.manage';

    // Prescriptions
    public const PRESCRIPTIONS_VIEW = 'prescriptions.view';

    public const PRESCRIPTIONS_CREATE = 'prescriptions.create';

    public const PRESCRIPTIONS_EDIT = 'prescriptions.edit';

    // Treatments
    public const TREATMENTS_VIEW = 'treatments.view';

    public const TREATMENTS_CREATE = 'treatments.create';

    public const TREATMENTS_EDIT = 'treatments.edit';

    // IPD
    public const IPD_VIEW = 'ipd.view';

    public const IPD_ADMIT = 'ipd.admit';

    public const IPD_DISCHARGE = 'ipd.discharge';

    public const IPD_NOTES = 'ipd.notes';

    // Billing
    public const BILLING_VIEW = 'billing.view';

    public const BILLING_CREATE = 'billing.create';

    public const BILLING_REFUND = 'billing.refund';

    public const CASH_REGISTER_MANAGE = 'cash_register.manage';

    // AI
    public const AI_USE = 'ai.use';

    public const AI_PROMPTS_MANAGE = 'ai.prompts.manage';

    // Notification templates
    public const NOTIFICATIONS_MANAGE = 'notifications.manage';

    // Staff management (doctor onboarding, availability)
    public const STAFF_MANAGE = 'staff.manage';

    // Treatment catalogue configuration
    public const TREATMENTS_MANAGE = 'treatments.manage';

    // IPD configuration (wards/rooms/beds)
    public const IPD_CONFIGURE = 'ipd.configure';

    // Documents
    public const DOCUMENTS_VIEW = 'documents.view';

    public const DOCUMENTS_UPLOAD = 'documents.upload';

    public const DOCUMENTS_DELETE = 'documents.delete';

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    /** @return array<string, list<string>> */
    public static function grouped(): array
    {
        $groups = [
            'patients' => self::patients(),
            'appointments' => self::appointments(),
            'consultations' => self::consultations(),
            'prescriptions' => self::prescriptions(),
            'treatments' => self::treatments(),
            'ipd' => self::ipd(),
            'billing' => self::billing(),
            'ai' => self::ai(),
            'documents' => self::documents(),
            'notifications' => self::notifications(),
            'staff' => self::staff(),
            'treatments' => array_merge(self::treatments(), [self::TREATMENTS_MANAGE]),
            'ipd' => array_merge(self::ipd(), [self::IPD_CONFIGURE]),
        ];

        return $groups;
    }

    /** @return list<string> */
    public static function patients(): array
    {
        return [
            self::PATIENTS_VIEW, self::PATIENTS_CREATE, self::PATIENTS_EDIT,
            self::PATIENTS_DELETE, self::PATIENTS_EXPORT,
        ];
    }

    /** @return list<string> */
    public static function appointments(): array
    {
        return [
            self::APPOINTMENTS_VIEW, self::APPOINTMENTS_CREATE, self::APPOINTMENTS_EDIT,
            self::APPOINTMENTS_CANCEL, self::QUEUE_MANAGE,
        ];
    }

    /** @return list<string> */
    public static function consultations(): array
    {
        return [
            self::CONSULTATIONS_VIEW, self::CONSULTATIONS_CREATE,
            self::CONSULTATIONS_EDIT, self::VITALS_MANAGE,
        ];
    }

    /** @return list<string> */
    public static function prescriptions(): array
    {
        return [
            self::PRESCRIPTIONS_VIEW, self::PRESCRIPTIONS_CREATE,
            self::PRESCRIPTIONS_EDIT,
        ];
    }

    /** @return list<string> */
    public static function treatments(): array
    {
        return [
            self::TREATMENTS_VIEW, self::TREATMENTS_CREATE, self::TREATMENTS_EDIT,
        ];
    }

    /** @return list<string> */
    public static function ipd(): array
    {
        return [
            self::IPD_VIEW, self::IPD_ADMIT, self::IPD_DISCHARGE, self::IPD_NOTES,
        ];
    }

    /** @return list<string> */
    public static function billing(): array
    {
        return [
            self::BILLING_VIEW, self::BILLING_CREATE, self::BILLING_REFUND,
            self::CASH_REGISTER_MANAGE,
        ];
    }

    /** @return list<string> */
    public static function ai(): array
    {
        return [self::AI_USE, self::AI_PROMPTS_MANAGE];
    }

    /** @return list<string> */
    public static function documents(): array
    {
        return [
            self::DOCUMENTS_VIEW, self::DOCUMENTS_UPLOAD, self::DOCUMENTS_DELETE,
        ];
    }

    /** @return list<string> */
    public static function notifications(): array
    {
        return [self::NOTIFICATIONS_MANAGE];
    }

    /** @return list<string> */
    public static function staff(): array
    {
        return [self::STAFF_MANAGE];
    }
}
