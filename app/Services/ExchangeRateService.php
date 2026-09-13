<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    /**
     * The current USD to BRL exchange rate, cached for an hour.
     *
     * Falls back to a configured rate when the exchange rate API is unreachable.
     */
    public function usdToBrl(): float
    {
        return Cache::remember('exchange-rate:usd-brl', now()->addHour(), function () {
            try {
                $response = Http::connectTimeout(3)->timeout(5)->get(config('services.exchange_rate.url'));
            } catch (ConnectionException) {
                return (float) config('services.exchange_rate.fallback_usd_brl');
            }

            $bid = $response->successful() ? data_get($response->json(), 'USDBRL.bid') : null;

            return is_numeric($bid) ? (float) $bid : (float) config('services.exchange_rate.fallback_usd_brl');
        });
    }
}
