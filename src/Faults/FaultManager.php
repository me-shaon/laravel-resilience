<?php

namespace MeShaon\LaravelResilience\Faults;

final class FaultManager
{
    /**
     * @var array<string, FaultRule>
     */
    private array $rules = [];

    /**
     * @var array<string, int>
     */
    private array $attempts = [];

    public function activate(FaultRule $rule): FaultRule
    {
        $this->rules[$rule->target()->key()] = $rule;
        $this->attempts[$rule->target()->key()] = 0;

        return $rule;
    }

    /**
     * @return array<int, FaultRule>
     */
    public function activeRules(): array
    {
        return array_values($this->rules);
    }

    public function deactivate(FaultTarget $target): void
    {
        unset($this->rules[$target->key()]);
        unset($this->attempts[$target->key()]);
    }

    public function deactivateAll(): void
    {
        $this->rules = [];
        $this->attempts = [];
    }

    public function deactivateScope(FaultScope $scope): void
    {
        foreach ($this->rules as $targetKey => $rule) {
            if ($rule->scope() === $scope) {
                unset($this->rules[$targetKey]);
                unset($this->attempts[$targetKey]);
            }
        }
    }

    public function ruleFor(FaultTarget $target): ?FaultRule
    {
        return $this->rules[$target->key()] ?? null;
    }

    public function ruleForAttempt(FaultTarget $target, int $attempt): ?FaultRule
    {
        $rule = $this->ruleFor($target);

        if ($rule === null) {
            return null;
        }

        return $rule->activatesOn($attempt)
            ? $rule
            : null;
    }

    public function shouldActivate(FaultTarget $target, int $attempt): bool
    {
        return $this->ruleForAttempt($target, $attempt) !== null;
    }

    public function triggeredRule(FaultTarget $target): ?FaultRule
    {
        $rule = $this->ruleFor($target);

        if ($rule === null) {
            return null;
        }

        $attempt = ($this->attempts[$target->key()] ?? 0) + 1;
        $this->attempts[$target->key()] = $attempt;

        return $rule->activatesOn($attempt)
            ? $rule
            : null;
    }
}
