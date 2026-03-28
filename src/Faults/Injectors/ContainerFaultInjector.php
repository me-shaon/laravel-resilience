<?php

namespace MeShaon\LaravelResilience\Faults\Injectors;

use Illuminate\Contracts\Container\Container;
use MeShaon\LaravelResilience\Exceptions\InvalidFaultConfiguration;
use MeShaon\LaravelResilience\Faults\FaultManager;
use MeShaon\LaravelResilience\Faults\FaultRule;
use MeShaon\LaravelResilience\Faults\FaultTarget;
use MeShaon\LaravelResilience\Faults\FaultType;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

final class ContainerFaultInjector
{
    /**
     * @var array<string, list<string>>
     */
    private const NAMED_DRIVER_METHODS = [
        'cache' => ['store', 'driver'],
        'mail.manager' => ['mailer', 'driver'],
        'queue' => ['connection'],
        'filesystem' => ['disk', 'drive'],
    ];

    /**
     * @var array<string, array{
     *     alias: string,
     *     originally_bound: bool,
     *     original_concrete: mixed,
     *     original_shared: bool
     * }>
     */
    private array $snapshots = [];

    public function __construct(
        private readonly Container $container,
        private readonly FaultManager $faultManager
    ) {}

    public function activate(FaultRule $rule): void
    {
        if ($rule->target()->type() !== 'container') {
            return;
        }

        $this->ensureSupportedRule($rule);
        $this->instrument($this->baseAbstract($rule->target()->name()));
    }

    public function baseAbstract(string $target): string
    {
        return str_contains($target, '::')
            ? explode('::', $target, 2)[0]
            : $target;
    }

    public function restore(string $abstract): void
    {
        if (! isset($this->snapshots[$abstract])) {
            return;
        }

        $snapshot = $this->snapshots[$abstract];

        $this->container->forgetInstance($abstract);
        $this->container->forgetInstance($snapshot['alias']);
        $this->container->offsetUnset($abstract);

        if ($snapshot['originally_bound']) {
            if ($snapshot['original_shared']) {
                $this->container->singleton($abstract, $snapshot['original_concrete']);
            } else {
                $this->container->bind($abstract, $snapshot['original_concrete']);
            }
        }

        $this->container->offsetUnset($snapshot['alias']);

        unset($this->snapshots[$abstract]);
    }

    public function restoreAll(): void
    {
        foreach (array_keys($this->snapshots) as $abstract) {
            $this->restore($abstract);
        }
    }

    private function callTarget(object $target, string $method, array $arguments): mixed
    {
        return $target->{$method}(...$arguments);
    }

    private function ensureResolvable(string $abstract): void
    {
        if ($this->container->bound($abstract)) {
            return;
        }

        if (interface_exists($abstract)) {
            throw InvalidFaultConfiguration::because(sprintf(
                'Container target [%s] is not bound in the container.',
                $abstract
            ));
        }

        if (! class_exists($abstract)) {
            throw InvalidFaultConfiguration::because(sprintf(
                'Container target [%s] does not exist.',
                $abstract
            ));
        }
    }

    private function ensureSupportedRule(FaultRule $rule): void
    {
        if (! in_array($rule->type(), [FaultType::Exception, FaultType::Timeout, FaultType::Latency], true)) {
            throw InvalidFaultConfiguration::because(sprintf(
                'Container injection currently supports [%s], [%s], and [%s] rules. [%s] was given.',
                FaultType::Exception->value,
                FaultType::Timeout->value,
                FaultType::Latency->value,
                $rule->type()->value
            ));
        }
    }

    private function instrument(string $abstract): void
    {
        if (isset($this->snapshots[$abstract])) {
            $this->container->forgetInstance($abstract);

            return;
        }

        $this->ensureResolvable($abstract);

        $bindings = $this->container->getBindings();
        $binding = $bindings[$abstract] ?? null;
        $originallyBound = $binding !== null;
        $originalConcrete = $binding['concrete'] ?? $abstract;
        $originalShared = (bool) ($binding['shared'] ?? false);
        $alias = $this->aliasFor($abstract);

        if ($originalShared) {
            $this->container->singleton($alias, $originalConcrete);
        } else {
            $this->container->bind($alias, $originalConcrete);
        }

        $proxyResolver = function (Container $app) use ($abstract, $alias): object {
            $target = $app->make($alias);

            return $this->createProxy(
                $target,
                $abstract,
                get_class($target),
                fn (object $resolvedTarget, string $method, array $arguments): mixed => $this->invoke(
                    $abstract,
                    $resolvedTarget,
                    $method,
                    $arguments
                )
            );
        };

        if ($originalShared) {
            $this->container->singleton($abstract, $proxyResolver);
        } else {
            $this->container->bind($abstract, $proxyResolver);
        }

        $this->container->forgetInstance($abstract);
        $this->container->forgetInstance($alias);

        $this->snapshots[$abstract] = [
            'alias' => $alias,
            'originally_bound' => $originallyBound,
            'original_concrete' => $originalConcrete,
            'original_shared' => $originalShared,
        ];
    }

    private function invoke(string $abstract, object $target, string $method, array $arguments): mixed
    {
        $rule = $this->faultManager->triggeredRule(FaultTarget::container($abstract));

        if ($rule !== null) {
            return match ($rule->type()) {
                FaultType::Exception,
                FaultType::Timeout => throw $this->exceptionFor($rule),
                FaultType::Latency => $this->invokeWithLatency($target, $method, $arguments, $rule->latencyInMilliseconds() ?? 0),
                default => $this->callTarget($target, $method, $arguments),
            };
        }

        $namedRule = $this->matchingNamedRule($abstract, $method, $arguments);

        if ($namedRule === null) {
            return $this->callTarget($target, $method, $arguments);
        }

        $resolvedTarget = $this->callTarget($target, $method, $arguments);

        if (! is_object($resolvedTarget)) {
            return $resolvedTarget;
        }

        return $this->createProxy(
            $resolvedTarget,
            $namedRule->target()->name(),
            get_class($resolvedTarget),
            fn (object $proxyTarget, string $proxyMethod, array $proxyArguments): mixed => $this->invokeNamedRule(
                $namedRule,
                $proxyTarget,
                $proxyMethod,
                $proxyArguments
            )
        );
    }

    private function invokeWithLatency(object $target, string $method, array $arguments, int $latencyInMilliseconds): mixed
    {
        usleep($latencyInMilliseconds * 1000);

        return $this->callTarget($target, $method, $arguments);
    }

    private function aliasFor(string $abstract): string
    {
        return 'laravel_resilience.original.'.sha1($abstract);
    }

    private function exceptionFor(FaultRule $rule): Throwable
    {
        return $rule->exceptionToThrow()
            ?? InvalidFaultConfiguration::because(sprintf(
                'Fault rule [%s] did not provide an exception for [%s].',
                $rule->name(),
                $rule->type()->value
            ));
    }

    private function invokeNamedRule(FaultRule $rule, object $target, string $method, array $arguments): mixed
    {
        $triggeredRule = $this->faultManager->triggeredRule($rule->target());

        if ($triggeredRule === null) {
            return $this->callTarget($target, $method, $arguments);
        }

        return match ($triggeredRule->type()) {
            FaultType::Exception,
            FaultType::Timeout => throw $this->exceptionFor($triggeredRule),
            FaultType::Latency => $this->invokeWithLatency($target, $method, $arguments, $triggeredRule->latencyInMilliseconds() ?? 0),
            default => $this->callTarget($target, $method, $arguments),
        };
    }

    private function matchingNamedRule(string $abstract, string $method, array $arguments): ?FaultRule
    {
        $namedMethodSet = self::NAMED_DRIVER_METHODS[$abstract] ?? null;

        if ($namedMethodSet === null || ! in_array($method, $namedMethodSet, true)) {
            return null;
        }

        $name = $arguments[0] ?? null;

        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return $this->faultManager->ruleFor(FaultTarget::container(sprintf('%s::%s', $abstract, $name)));
    }

    /**
     * @throws ReflectionException
     */
    private function buildMethodCode(ReflectionMethod $method): string
    {
        if ($method->returnsReference()) {
            throw InvalidFaultConfiguration::because(sprintf(
                'Container target [%s::%s] cannot be proxied because it returns by reference.',
                $method->getDeclaringClass()->getName(),
                $method->getName()
            ));
        }

        $parameters = [];
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isPassedByReference()) {
                throw InvalidFaultConfiguration::because(sprintf(
                    'Container target [%s::%s] cannot be proxied because it uses by-reference parameters.',
                    $method->getDeclaringClass()->getName(),
                    $method->getName()
                ));
            }

            $parameters[] = $this->parameterCode($parameter);
            $arguments[] = $parameter->isVariadic()
                ? '...$'.$parameter->getName()
                : '$'.$parameter->getName();
        }

        $returnType = $method->hasReturnType()
            ? ': '.$this->typeCode($method->getReturnType())
            : '';

        $signature = sprintf(
            'public function %s(%s)%s',
            $method->getName(),
            implode(', ', $parameters),
            $returnType
        );

        $invocation = sprintf(
            '$this->__laravelResilienceInvoke(%s, [%s])',
            var_export($method->getName(), true),
            implode(', ', $arguments)
        );

        if ($method->hasReturnType() && $method->getReturnType() instanceof ReflectionNamedType && $method->getReturnType()->getName() === 'void') {
            return $signature." {\n        ".$invocation.";\n    }";
        }

        return $signature." {\n        return ".$invocation.";\n    }";
    }

    /**
     * @throws ReflectionException
     */
    private function createProxy(object $target, string $abstract, string $proxyableClass, callable $interceptor): object
    {
        $proxyClass = $this->proxyClassFor($abstract, $proxyableClass);

        return new $proxyClass($target, \Closure::fromCallable($interceptor));
    }

    /**
     * @throws ReflectionException
     */
    private function proxyClassFor(string $abstract, string $proxyableClass): string
    {
        $className = 'LaravelResilienceProxy_'.sha1($abstract.'|'.$proxyableClass);

        if (class_exists($className, false)) {
            return $className;
        }

        $reflection = new ReflectionClass($proxyableClass);

        if ($reflection->isFinal()) {
            throw InvalidFaultConfiguration::because(sprintf(
                'Container target [%s] cannot be proxied because resolved class [%s] is final.',
                $abstract,
                $proxyableClass
            ));
        }

        $methods = array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $method): bool => ! $method->isStatic() && ! $method->isConstructor() && ! $method->isDestructor()
        );

        foreach ($methods as $method) {
            if ($method->isFinal()) {
                throw InvalidFaultConfiguration::because(sprintf(
                    'Container target [%s] cannot be proxied because resolved class [%s] has final method [%s].',
                    $abstract,
                    $proxyableClass,
                    $method->getName()
                ));
            }
        }

        $targetDeclaration = 'extends \\'.$proxyableClass;

        $methodCode = implode("\n\n    ", array_map(
            fn (ReflectionMethod $method): string => $this->buildMethodCode($method),
            $methods
        ));

        eval(sprintf(
            'class %s %s {
                private object $__laravelResilienceTarget;
                private \Closure $__laravelResilienceInterceptor;

                public function __construct(object $target, \Closure $interceptor)
                {
                    $this->__laravelResilienceTarget = $target;
                    $this->__laravelResilienceInterceptor = $interceptor;
                }

                private function __laravelResilienceInvoke(string $method, array $arguments): mixed
                {
                    return ($this->__laravelResilienceInterceptor)($this->__laravelResilienceTarget, $method, $arguments);
                }

                %s
            }',
            $className,
            $targetDeclaration,
            $methodCode
        ));

        return $className;
    }

    private function parameterCode(ReflectionParameter $parameter): string
    {
        $parts = [];

        if ($parameter->hasType()) {
            $parts[] = $this->typeCode($parameter->getType());
        }

        if ($parameter->isVariadic()) {
            $parts[] = '...$'.$parameter->getName();

            return implode(' ', $parts);
        }

        $parts[] = '$'.$parameter->getName();

        if ($parameter->isOptional() && ! $parameter->isDefaultValueAvailable()) {
            return implode(' ', $parts);
        }

        if ($parameter->isDefaultValueAvailable()) {
            $default = $parameter->isDefaultValueConstant()
                ? $parameter->getDefaultValueConstantName()
                : var_export($parameter->getDefaultValue(), true);

            $parts[] = '= '.$default;
        }

        return implode(' ', $parts);
    }

    private function typeCode(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            $prefix = $type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null' ? '?' : '';
            $name = $type->isBuiltin() ? $type->getName() : '\\'.$type->getName();

            return $prefix.$name;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn (ReflectionType $member): string => $this->typeCode($member),
                $type->getTypes()
            ));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(
                fn (ReflectionType $member): string => $this->typeCode($member),
                $type->getTypes()
            ));
        }

        throw InvalidFaultConfiguration::because('Unsupported reflection type encountered while generating a container proxy.');
    }
}
