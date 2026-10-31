<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport;

use ArrayObject;
use Closure;
use Derafu\File\Directory;
use Derafu\Support\Csv;
use Derafu\Support\Date;
use Derafu\Support\Factory;
use Derafu\Support\Hydrator;
use Derafu\Support\Str;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * What the helpers report when they fail is a translatable error, of the same
 * kind as before and saying the same.
 */
#[CoversClass(Csv::class)]
#[CoversClass(Date::class)]
#[CoversClass(Factory::class)]
#[CoversClass(Hydrator::class)]
#[CoversClass(Str::class)]
final class TranslatableErrorsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu-support-' . uniqid('', true);
        mkdir($this->directory);

        file_put_contents($this->directory . '/text.txt', 'hello');
    }

    protected function tearDown(): void
    {
        Directory::remove($this->directory);
    }

    /**
     * @return array<string, array{Closure, class-string<Throwable>, string}>
     */
    private function cases(): array
    {
        return [
            'Csv::read of a file that does not exist' => [
                fn () => Csv::read('/no/such.csv'),
                RuntimeException::class,
                'Cannot read file: /no/such.csv',
            ],
            'Csv::write in a directory that can not be used' => [
                fn () => Csv::write([], '/no/dir/file.csv'),
                LogicException::class,
                'Cannot use directory: /no/dir',
            ],
            'Csv::load with a wrong escape' => [
                fn () => Csv::load('x', ';', '"', 'ab'),
                RuntimeException::class,
                'Failed to parse CSV content: League\Csv\AbstractCsv::setEscape() expects escape to be a single character or an empty string; `ab` given.',
            ],
            'Csv::generate with a wrong escape' => [
                fn () => Csv::generate([[1]], ';', '"', 'ab'),
                RuntimeException::class,
                'Failed to generate CSV: League\Csv\AbstractCsv::setEscape() expects escape to be a single character or an empty string; `ab` given.',
            ],
            'Date::formatPeriodSpanish' => [
                fn () => Date::formatPeriodSpanish(202413),
                InvalidArgumentException::class,
                'Invalid month in period: 202413',
            ],
            'Date::periodToCarbon' => [
                fn () => Date::periodToCarbon(202413),
                InvalidArgumentException::class,
                'Invalid month in period: 202413',
            ],
            'Date::nextDate' => [
                fn () => Date::nextDate('2024-01-01', 'X'),
                InvalidArgumentException::class,
                'Invalid time unit: X',
            ],
            'Date::previousDate' => [
                fn () => Date::previousDate('2024-01-01', 'X'),
                InvalidArgumentException::class,
                'Invalid time unit: X',
            ],
            'Str::format with an unknown style' => [
                fn () => Str::format('x', [], 'nope'),
                InvalidArgumentException::class,
                'Unsupported placeholder style: nope.',
            ],
            'Str::random with a length of zero' => [
                fn () => Str::random(0),
                InvalidArgumentException::class,
                'Length must be at least 1.',
            ],
            'Factory::createAndEnsureType' => [
                fn () => Factory::createAndEnsureType(['a' => 1], stdClass::class, ArrayObject::class),
                LogicException::class,
                'Created instance of stdClass does not match expected type ArrayObject.',
            ],
            'Hydrator::hydrate of a property that does not exist' => [
                fn () => Hydrator::hydrate(new ErrorsTarget(), ['missing' => 1]),
                LogicException::class,
                'Cannot assign attribute "missing" to class ' . ErrorsTarget::class . '. No property or suitable setter method found.',
            ],
            'Hydrator::createAndHydrate of a class that does not exist' => [
                // A class that does not exist, on purpose.
                // @phpstan-ignore argument.type (a class that does not exist, on purpose)
                fn () => Hydrator::createAndHydrate('NoSuchClass', []),
                LogicException::class,
                'Failed to create instance of NoSuchClass: Class "NoSuchClass" does not exist',
            ],
        ];
    }

    public function testEveryErrorIsTranslatableAndSaysTheSame(): void
    {
        foreach ($this->cases() as $name => [$trigger, $class, $message]) {
            $exception = null;
            try {
                $trigger();
            } catch (Throwable $e) {
                $exception = $e;
            }

            $this->assertInstanceOf($class, $exception, $name);
            $this->assertInstanceOf(TranslatableInterface::class, $exception, $name);
            $this->assertSame($message, $exception->getMessage(), $name);
        }
    }
}

/**
 * A class without properties, to hydrate something that is not there.
 */
final class ErrorsTarget
{
}
