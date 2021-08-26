<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use InvalidArgumentException;
use ReflectionException;
use ReflectionProperty;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\CommandWithChildren;
use Webmozart\Assert\Assert;

use function count;
use function reset;

final class TestHelper
{
    /**
     * @return Command[]
     *
     * @throws InvalidArgumentException|ReflectionException
     */
    public static function childrenForCommand(CommandWithChildren $commandWithChildren): array
    {
        $childrenProperty = new ReflectionProperty($commandWithChildren, 'children');
        $childrenProperty->setAccessible(true);
        $children = $childrenProperty->getValue($commandWithChildren);

        Assert::isArray($children);
        Assert::allIsInstanceOf($children, Command::class);

        return $children;
    }

    public static function firstChildForCommand(CommandWithChildren $commandWithChildren): Command
    {
        $children = self::childrenForCommand($commandWithChildren);

        Assert::greaterThanEq(count($children), 1);

        return reset($children);
    }
}
