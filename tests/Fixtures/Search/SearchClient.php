<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Search;

class SearchClient
{
    public function search(string $query): string
    {
        return 'results:'.$query;
    }
}
