<?php

namespace MeShaon\LaravelResilience\Scaffolding;

enum ScaffoldMode: string
{
    case Create = 'create';
    case Update = 'update';
    case Force = 'force';
}
