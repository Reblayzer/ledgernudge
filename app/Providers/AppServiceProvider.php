<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\Claude\AnthropicMessenger;
use App\Services\Claude\Contracts\ClaudeMessenger;
use App\Services\Sending\Contracts\SmsGateway;
use App\Services\Sending\TwilioSmsGateway;
use App\Services\Stripe\Contracts\CheckoutGateway;
use App\Services\Stripe\StripeCheckoutGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->bind(SmsGateway::class, fn () => new TwilioSmsGateway(
            sid: config('services.twilio.sid'),
            token: config('services.twilio.token'),
            from: config('services.twilio.from'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Basic queue rate limiting for the send pipeline.
        RateLimiter::for('dunning-sends', fn () => Limit::perMinute(60));
    }
}
