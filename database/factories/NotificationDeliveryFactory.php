<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'notification_template_id' => null,
            'notification_id' => null,
            'notifiable_type' => Patient::class,
            'notifiable_id' => Patient::factory(),
            'channel' => fake()->randomElement(['in_app', 'whatsapp', 'sms', 'email']),
            'event_id' => fake()->optional()->numerify('evt-####'),
            'recipient' => fake()->optional()->phoneNumber(),
            'status' => 'PENDING',
            'provider_reference' => null,
            'error' => null,
            'attempts' => 0,
            'sent_at' => null,
            'delivered_at' => null,
        ];
    }
}
