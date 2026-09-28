<?php

namespace Tests\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\FunctionParameterClosureThisExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Tests\TestCase;

/**
 * Pest annotates test closures as bound to TestCall, but at runtime they are bound to the suite's test case:
 * tests/Pest.php and tests/Manual extend Tests\TestCase, and every other suite runs on PHPUnit's TestCase.
 */
final class PestClosureThisExtension implements FunctionParameterClosureThisExtension
{
    private const array FUNCTIONS = ['test', 'it', 'beforeEach', 'afterEach'];

    private const array LARAVEL_SUITES = ['Feature', 'Manual'];

    public function isFunctionSupported(FunctionReflection $functionReflection, ParameterReflection $parameter): bool
    {
        return in_array($functionReflection->getName(), self::FUNCTIONS, true) && $parameter->getName() === 'closure';
    }

    public function getClosureThisTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, ParameterReflection $parameter, Scope $scope): Type
    {
        foreach (self::LARAVEL_SUITES as $suite) {
            if (str_contains($scope->getFile(), DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.$suite.DIRECTORY_SEPARATOR)) {
                return new ObjectType(TestCase::class);
            }
        }

        return new ObjectType(PHPUnitTestCase::class);
    }
}
