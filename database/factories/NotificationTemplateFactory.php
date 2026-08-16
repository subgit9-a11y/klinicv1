<?php

namespace Database\Factories;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'event_key' => fake()->randomElement(['appointment.confirmation', 'appointment.reminder', 'ipd.admission']),
            'channel' => fake()->randomElement(['in_app', 'whatsapp', 'sms', 'email']),
            'name' => fake()->words(3, true),
            'subject' => fake()->optional()->sentence(),
            'body' => 'Hello {{patient_name}}, your appointment is confirmed for {{appointment_time}}.',
            'whatsapp_template_name' => fake()->optional()->word(),
            'sms_template_id' => fake()->optional()->numerify('####'),
            'is_active' => true,
        ];
    }
}
