<?php

namespace MeShaon\LaravelResilience\Faults;

enum FaultScope: string
{
    case Test = 'test';
    case Process = 'process';
}
