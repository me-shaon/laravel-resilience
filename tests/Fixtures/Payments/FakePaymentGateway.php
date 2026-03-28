<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Payments;

class FakePaymentGateway implements PaymentGateway
{
    public function charge(int $amount): string
    {
        return 'charged:'.$amount;
    }
}
