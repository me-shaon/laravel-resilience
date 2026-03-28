<?php

namespace MeShaon\LaravelResilience\Tests\Fixtures\Search;

class FakeSearchClient extends SearchClient
{
    public function search(string $query): string
    {
        return parent::search($query);
    }
}
