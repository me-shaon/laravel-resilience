<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Discovery;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GuardedBillingService
{
    /**
     * @return array{ok: bool}
     */
    public function sync(): array
    {
        try {
            $response = Http::retry(3, 100)
                ->timeout(5)
                ->post('https://example.com/api/billing', ['id' => 1]);

            return ['ok' => $response->successful()];
        } catch (RuntimeException) {
            return ['ok' => false];
        }
    }
}
