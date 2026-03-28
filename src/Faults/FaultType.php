<?php

namespace MeShaon\LaravelResilience\Faults;

enum FaultType: string
{
    case Exception = 'exception';
    case FailFirstN = 'fail-first-n';
    case Latency = 'latency';
    case Percentage = 'percentage';
    case RecoverAfterNAttempts = 'recover-after-n-attempts';
    case Timeout = 'timeout';
}
