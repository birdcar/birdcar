<?php

namespace Tests\PHPStan;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Testing\PendingCommand;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Laravel types artisan() as PendingCommand|int because it returns the exit code when console output is not mocked.
 * This suite never disables console mocking, so every call returns a PendingCommand.
 */
final class ArtisanPendingCommandExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return TestCase::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'artisan';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        return new ObjectType(PendingCommand::class);
    }
}
