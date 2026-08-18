<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the default global notification templates (tenant_id = null) so the
 * app has working notifications for key events out of the box. Tenants can
 * add overrides via the NotificationTemplateService.
 */
class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'event_key' => 'appointment.confirmation',
                'channel' => 'in_app',
                'name' => 'Appointment Confirmation (In-App)',
                'subject' => 'Appointment booked',
                'body' => 'Your appointment with {{doctor_name}} is scheduled for {{appointment_date}} at {{start_time}}.',
            ],
            [
                'event_key' => 'appointment.confirmation',
                'channel' => 'sms',
                'name' => 'Appointment Confirmation (SMS)',
                'subject' => null,
                'body' => 'Hello {{patient_name}}, your appointment with {{doctor_name}} is confirmed for {{appointment_date}} at {{appointment_time}}. - Klinic360',
            ],
            [
                'event_key' => 'appointment.reminder',
                'channel' => 'sms',
                'name' => 'Appointment Reminder (SMS)',
                'subject' => null,
                'body' => 'Reminder: {{patient_name}}, your appointment is tomorrow ({{appointment_date}}) at {{appointment_time}}. Reply C to confirm or R to reschedule. - Klinic360',
            ],
            [
                'event_key' => 'appointment.confirmation',
                'channel' => 'whatsapp',
                'name' => 'Appointment Confirmation (WhatsApp)',
                'subject' => null,
                'body' => 'Hi {{patient_name}}, your appointment with {{doctor_name}} on {{appointment_date}} at {{appointment_time}} is confirmed.',
                'whatsapp_template_name' => 'appointment_confirmation',
            ],
            [
                'event_key' => 'appointment.confirmation',
                'channel' => 'email',
                'name' => 'Appointment Confirmation (Email)',
                'subject' => 'Your appointment is confirmed - {{clinic_name}}',
                'body' => "Dear {{patient_name}},\n\nYour appointment with Dr. {{doctor_name}} is confirmed for {{appointment_date}} at {{appointment_time}}.\n\nThank you,\n{{clinic_name}}",
            ],
            [
                'event_key' => 'appointment.reminder',
                'channel' => 'email',
                'name' => 'Appointment Reminder (Email)',
                'subject' => 'Reminder: appointment tomorrow - {{clinic_name}}',
                'body' => "Dear {{patient_name}},\n\nThis is a reminder for your appointment tomorrow ({{appointment_date}}) at {{appointment_time}} with Dr. {{doctor_name}}.\n\nThank you,\n{{clinic_name}}",
            ],
            [
                'event_key' => 'ipd.admission',
                'channel' => 'in_app',
                'name' => 'IPD Admission (In-App)',
                'subject' => 'Patient admitted',
                'body' => '{{patient_name}} has been admitted. Bed: {{bed_number}}, Ward: {{ward_name}}.',
            ],
            [
                'event_key' => 'ipd.discharge',
                'channel' => 'in_app',
                'name' => 'IPD Discharge (In-App)',
                'subject' => 'Patient discharged',
                'body' => '{{patient_name}} has been discharged. Follow-up in {{follow_up_days}} days.',
            ],
            [
                'event_key' => 'invoice.issued',
                'channel' => 'email',
                'name' => 'Invoice Issued (Email)',
                'subject' => 'Invoice {{invoice_number}} from {{clinic_name}}',
                'body' => "Dear {{patient_name}},\n\nYour invoice {{invoice_number}} for {{currency}} {{amount}} has been generated. Due: {{due_date}}.\n\nThank you,\n{{clinic_name}}",
            ],
            [
                'event_key' => 'payment.received',
                'channel' => 'in_app',
                'name' => 'Payment Received (In-App)',
                'subject' => 'Payment recorded',
                'body' => 'We received your payment of {{currency}} {{amount}} for invoice {{invoice_number}} ({{payment_number}}).',
            ],
            [
                'event_key' => 'payment.received',
                'channel' => 'sms',
                'name' => 'Payment Received (SMS)',
                'subject' => null,
                'body' => 'We received your payment of {{currency}} {{amount}} for invoice {{invoice_number}}. Thank you - {{clinic_name}}',
            ],
            [
                'event_key' => 'followup.due',
                'channel' => 'sms',
                'name' => 'Follow-up Due (SMS)',
                'subject' => null,
                'body' => 'Hi {{patient_name}}, this is a reminder for your follow-up visit scheduled for {{followup_date}}. - {{clinic_name}}',
            ],
        ];

        foreach ($templates as $tpl) {
            NotificationTemplate::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'tenant_id' => null,
                    'event_key' => $tpl['event_key'],
                    'channel' => $tpl['channel'],
                ],
                array_merge([
                    'is_active' => true,
                    'whatsapp_template_name' => $tpl['whatsapp_template_name'] ?? null,
                    'sms_template_id' => $tpl['sms_template_id'] ?? null,
                ], $tpl)
            );
        }
    }
}
