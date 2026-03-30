<?php

namespace MeShaon\LaravelResilience\Reporting;

enum ConsoleOutputMode: string
{
    case Compact = 'compact';
    case Default = 'default';
    case Verbose = 'verbose';
}
