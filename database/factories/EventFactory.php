<?php

namespace Database\Factories;

use App\Models\Debtor;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'debtor_id' => Debtor::factory(),
            'invoice_id' => null,
            'message_id' => null,
            'user_id' => null,
            'type' => Event::INVOICE_CREATED,
            'data' => [],
        ];
    }
}
