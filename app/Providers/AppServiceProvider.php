<?php

namespace App\Providers;

use App\Contracts\PaystackClientInterface;
use App\Services\Paystack\PaystackHttpClient;
use App\Services\Paystack\PaystackMockClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaystackClientInterface::class, function (): PaystackClientInterface {
            $config = config('services.paystack');
            $callbackUrl = (string) ($config['callback_url'] ?? 'http://localhost:4200/checkout/callback');

            if (filter_var($config['mock'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                return new PaystackMockClient($callbackUrl);
            }

            return new PaystackHttpClient(
                secretKey: (string) ($config['secret'] ?? ''),
                callbackUrl: $callbackUrl,
                baseUrl: (string) ($config['base_url'] ?? 'https://api.paystack.co'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('ticket-validate', function (Request $request) {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        if (class_exists(\Dedoc\Scramble\Scramble::class)) {
            \Dedoc\Scramble\Scramble::afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi, \Dedoc\Scramble\OpenApiContext $context): void {
                $openApi->secure(
                    \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer')
                        ->as('sanctum')
                        ->setDescription('Laravel Sanctum personal access token. Obtain a token from `POST /api/auth/login` (or `POST /api/auth/register`), then use `Authorization: Bearer {token}`.')
                );
            });
        }
    }
}
