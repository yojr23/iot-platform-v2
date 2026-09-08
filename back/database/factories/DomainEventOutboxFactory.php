<?php

namespace Database\Factories;

use App\Models\DomainEventOutbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DomainEventOutbox>
 */
class DomainEventOutboxFactory extends Factory
{
    protected $model = DomainEventOutbox::class;

    public function definition(): array
    {
        return [
            'event_type' => 'alert.resolved',
            'aggregate_type' => 'alert',
            'aggregate_id' => (string) $this->faker->numberBetween(1, 1000),
            'payload' => ['alert_id' => $this->faker->numberBetween(1, 1000)],
            'status' => 'pending',
            'attempts' => 0,
        ];
    }
}
