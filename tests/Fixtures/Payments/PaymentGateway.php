<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Payments;

interface PaymentGateway
{
    public function charge(int $amount): string;
}
