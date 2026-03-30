<?php

namespace MeShaon\LaravelResilience\Scaffolding;

use MeShaon\LaravelResilience\Suggestions\ResilienceSuggestion;

final class ScaffoldedItem
{
    public function __construct(
        private readonly string $hotspotId,
        private readonly string $status,
        private readonly string $outputPath,
        private readonly string $reason,
        private readonly ResilienceSuggestion $suggestion,
    ) {}

    public function hotspotId(): string
    {
        return $this->hotspotId;
    }

    public function outputPath(): string
    {
        return $this->outputPath;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function suggestion(): ResilienceSuggestion
    {
        return $this->suggestion;
    }
}
