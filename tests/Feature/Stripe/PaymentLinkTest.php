<?php

namespace Tests\Feature\Stripe;

use App\Models\Event;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Stripe\Contracts\CheckoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Checkout\Session;
use Tests\TestCase;

class PaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGatewayReturning(Session $session): void
    {
        $this->swap(CheckoutGateway::class, new class($session) implements CheckoutGateway
        {
            public function __construct(private Session $session) {}

            public function createSession(array $params): Session
            {
                return $this->session;
            }
        });
    }

    public function test_operator_can_create_a_payment_link_for_an_invoice(): void
    {
        $this->fakeGatewayReturning(Session::constructFrom([
            'id' => 'cs_test_abc',
            'url' => 'https://checkout.stripe.test/c/cs_test_abc',
            'payment_intent' => 'pi_test_abc',
        ]));

        $invoice = Invoice::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->post("/invoices/{$invoice->id}/payment-link");

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertSame('cs_test_abc', $invoice->stripe_checkout_session_id);
        $this->assertSame('https://checkout.stripe.test/c/cs_test_abc', $invoice->payment_url);
        $this->assertDatabaseHas('events', [
            'invoice_id' => $invoice->id,
            'type' => Event::PAYMENT_LINK_CREATED,
        ]);
    }

    public function test_creating_a_payment_link_requires_authentication(): void
    {
        $invoice = Invoice::factory()->create();

        $this->post("/invoices/{$invoice->id}/payment-link")
            ->assertRedirect('/login');
    }
}
