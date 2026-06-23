<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\Claude\AnthropicMessenger;
use App\Services\Claude\Contracts\ClaudeMessenger;
use App\Services\Stripe\Contracts\CheckoutGateway;
use App\Services\Stripe\StripeCheckoutGateway;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, fn () => new StripeClient([
            'api_key' => config('services.stripe.secret'),
        ]));

        $this->app->bind(CheckoutGateway::class, StripeCheckoutGateway::class);

        $this->app->singleton(AnthropicClient::class, fn () => new AnthropicClient(
            apiKey: config('services.anthropic.key'),
        ));

        $this->app->bind(ClaudeMessenger::class, AnthropicMessenger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
