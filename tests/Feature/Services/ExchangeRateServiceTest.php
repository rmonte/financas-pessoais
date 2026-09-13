<?php

use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('returns the bid rate from the exchange rate API', function () {
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.4321']]),
    ]);

    expect((new ExchangeRateService)->usdToBrl())->toBe(5.4321);
});

test('falls back to the configured rate when the API request fails', function () {
    Http::fake([
        config('services.exchange_rate.url') => Http::failedConnection(),
    ]);

    expect((new ExchangeRateService)->usdToBrl())->toBe((float) config('services.exchange_rate.fallback_usd_brl'));
});

test('falls back to the configured rate when the API returns an unexpected payload', function () {
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['unexpected' => 'payload']),
    ]);

    expect((new ExchangeRateService)->usdToBrl())->toBe((float) config('services.exchange_rate.fallback_usd_brl'));
});

test('caches the rate so a second call does not hit the API again', function () {
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.10']]),
    ]);

    $service = new ExchangeRateService;
    $service->usdToBrl();
    $service->usdToBrl();

    Http::assertSentCount(1);
});
