<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport;

use Derafu\Support\Debug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Debug::class)]
class DebugTest extends TestCase
{
    #[Test]
    #[DataProvider('provideInspectData')]
    public function shouldInspectVariable(mixed $var, ?string $label, array $expected): void
    {
        $result = Debug::inspect($var, $label);

        // Verify presence of all required fields.
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('length', $result);
        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('line', $result);
        $this->assertArrayHasKey('caller', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('memory_usage', $result);
        $this->assertArrayHasKey('value', $result);

        // Assert specific expected values.
        $this->assertSame($expected['label'], $result['label']);
        $this->assertSame($expected['type'], $result['type']);
        $this->assertSame($expected['length'], $result['length']);

        // Verify file and line are present.
        $this->assertIsString($result['file']);
        $this->assertIsInt($result['line']);

        // Verify caller format
        $this->assertMatchesRegularExpression(
            '/^[\w\\\\]+::\w+\(\)$/',
            $result['caller']
        );

        // Verify timestamp and memory usage.
        $this->assertIsFloat($result['timestamp']);
        $this->assertIsInt($result['memory_usage']);

        // Verify value matches expected format.
        if (isset($expected['value_pattern'])) {
            $this->assertMatchesRegularExpression(
                $expected['value_pattern'],
                $result['value']
            );
        }
    }

    public static function provideInspectData(): array
    {
        $obj = new stdClass();
        $obj->test = 'value';

        return [
            'string variable' => [
                'test string',
                'myString',
                [
                    'label' => 'myString',
                    'type' => 'string',
                    'length' => 11,
                    'value_pattern' => '/^test string$/',
                ],
            ],
            'array variable' => [
                ['test', 123],
                'myArray',
                [
                    'label' => 'myArray',
                    'type' => 'array',
                    'length' => 2,
                    'value_pattern' => '/Array\s+\(\s+\[0\] => test\s+\[1\] => 123\s+\)/',
                ],
            ],
            'object variable' => [
                $obj,
                'myObject',
                [
                    'label' => 'myObject',
                    'type' => stdClass::class,
                    'length' => null,
                    'value_pattern' => '/stdClass Object\s+\(\s+\[test\] => value\s+\)/',
                ],
            ],
            'null value' => [
                null,
                'myNull',
                [
                    'label' => 'myNull',
                    'type' => 'NULL',
                    'length' => null,
                    'value_pattern' => '/null/',
                ],
            ],
            'boolean value' => [
                true,
                'myBool',
                [
                    'label' => 'myBool',
                    'type' => 'boolean',
                    'length' => null,
                    'value_pattern' => '/true/',
                ],
            ],
            'number value' => [
                42,
                'myNumber',
                [
                    'label' => 'myNumber',
                    'type' => 'integer',
                    'length' => null,
                    'value_pattern' => '/42/',
                ],
            ],
            'default label' => [
                'test',
                null,
                [
                    'label' => 'debug($var)',
                    'type' => 'string',
                    'length' => 4,
                    'value_pattern' => '/^test$/',
                ],
            ],
        ];
    }

    #[Test]
    public function shouldPrintDebugInformation(): void
    {
        // Start output buffering to capture printed content.
        ob_start();

        $testVar = ['test' => 'value'];
        Debug::print($testVar, 'testVar');

        $output = ob_get_clean();

        // Verify output format.
        $this->assertStringContainsString('<pre>', $output);
        $this->assertStringContainsString('</pre>', $output);

        // Verify content.
        $this->assertStringContainsString('testVar', $output);
        $this->assertStringContainsString('array', $output);
        $this->assertStringContainsString('test', $output);
        $this->assertStringContainsString('value', $output);
    }

    #[Test]
    public function shouldHandleRecursiveStructures(): void
    {
        $recursive = [];
        $recursive['self'] = &$recursive;

        $result = Debug::inspect($recursive, 'recursive');

        $this->assertSame('recursive', $result['label']);
        $this->assertSame('array', $result['type']);
        $this->assertSame(1, $result['length']);
        $this->assertStringContainsString('*RECURSION*', $result['value']);
    }

    #[Test]
    public function shouldHandleResources(): void
    {
        $resource = fopen('php://memory', 'r');

        $result = Debug::inspect($resource, 'resource');

        $this->assertSame('resource', $result['label']);
        $this->assertSame('resource', $result['type']);
        $this->assertNull($result['length']);
        $this->assertStringContainsString('Resource', $result['value']);

        fclose($resource);
    }

    #[Test]
    public function shouldHandleLongStrings(): void
    {
        $longString = str_repeat('a', 1000);

        $result = Debug::inspect($longString, 'longString');

        $this->assertSame('string', $result['type']);
        $this->assertSame(1000, $result['length']);
        $this->assertSame(1000, strlen($result['value']));
    }

    #[Test]
    public function shouldReportWhereDebugPrintWasCalled(): void
    {
        // The information is the one of the call of the user, not the one of
        // the code of the package.
        $line = __LINE__ + 2;
        ob_start();
        Debug::print('x', 'label');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('[file] => ' . __FILE__, $output);
        $this->assertStringContainsString('[line] => ' . $line, $output);
        $this->assertStringContainsString('[caller] => ' . self::class . '::shouldReportWhereDebugPrintWasCalled()', $output);
        $this->assertStringNotContainsString('src/Debug.php', $output);
    }

    #[Test]
    public function shouldReportTheCallerOfInspectFromAFunctionAndFromTheGlobalScope(): void
    {
        // A call from the code that is outside of any function (there is no
        // caller to report) must work as any other, without warnings. It needs a
        // script of its own: in a test there is always a function that calls.
        $script = tempnam(sys_get_temp_dir(), 'derafu-debug-') ?: '';
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';
function inAFunction() { return Derafu\Support\Debug::inspect(1); }
$global = Derafu\Support\Debug::inspect([1, 2], "global");
$function = inAFunction();
echo json_encode(["global" => $global, "function" => $function]);
');

        try {
            $output = (string) shell_exec(
                escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -d error_reporting=-1 '
                . escapeshellarg($script) . ' 2>&1'
            );
        } finally {
            unlink($script);
        }

        // Without warnings or notices, the output is only the JSON.
        $result = json_decode($output, true);
        $this->assertIsArray($result, $output);
        $this->assertSame('{main}', $result['global']['caller']);
        $this->assertSame($script, $result['global']['file']);
        $this->assertSame(4, $result['global']['line']);
        $this->assertSame('inAFunction()', $result['function']['caller']);
        $this->assertSame(3, $result['function']['line']);
    }
}
