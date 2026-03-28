<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Payments;

final class FakePaymentGateway implements PaymentGateway
{
    public function charge(int $amount): string
    {
        return 'charged:'.$amount;
    }
}
