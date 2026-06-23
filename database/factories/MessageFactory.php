<?php

namespace Database\Factories;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Debtor;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'debtor_id' => Debtor::factory(),
            'invoice_id' => null,
            'direction' => MessageDirection::Outbound,
            'channel' => fake()->randomElement(MessageChannel::cases()),
            'status' => MessageStatus::PendingApproval,
            'body' => fake()->paragraph(),
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn () => [
            'direction' => MessageDirection::Inbound,
            'status' => MessageStatus::Received,
        ]);
    }
}
