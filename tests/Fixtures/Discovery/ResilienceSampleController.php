<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Discovery;

use App\Payments\LegacyGatewayClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

final class ResilienceSampleController
{
    public function __construct(private LegacyGatewayClient $gatewayClient) {}

    public function handle(): void
    {
        Http::post('https://example.com/api/orders', ['id' => 1]);
        Mail::to('team@example.com')->send(new \stdClass);
        Queue::push(new \stdClass);
        Storage::put('orders/example.txt', 'hello');
        Cache::remember('orders.summary', 60, fn () => ['ok' => true]);

        $client = new LegacyGatewayClient;
    }
}
