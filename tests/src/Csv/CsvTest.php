<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport\Csv;

use Derafu\Support\Csv;
use Derafu\Support\File;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Csv::class)]
#[UsesClass(File::class)]
class CsvTest extends TestCase
{
    private string $fixturesDir;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__, 2) . '/fixtures/csv';
        $this->tempDir = sys_get_temp_dir() . '/derafu-support-' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::rmdir($this->tempDir);
    }

    #[Test]
    public function shouldLoadCsvFromString(): void
    {
        $csvString = "name;age;city\nJohn;25;New York\nJane;30;London";
        $result = Csv::load($csvString);

        $this->assertCount(3, $result);
        $this->assertSame('John', $result[1][0]);
        $this->assertSame('30', $result[2][1]);
        $this->assertSame('London', $result[2][2]);
    }

    #[Test]
    #[DataProvider('provideCsvData')]
    public function shouldReadCsvFile(
        string $filename,
        string $separator,
        array $expected
    ): void {
        $file = $this->fixturesDir . '/' . $filename;
        $result = Csv::read($file, $separator);
        $this->assertSame($expected, $result);
    }

    public static function provideCsvData(): array
    {
        return [
            'standard csv' => [
                'standard.csv',
                ';',
                [
                    ['name', 'age', 'city'],
                    ['John', '25', 'New York'],
                    ['Jane', '30', 'London'],
                ],
            ],
            'different separator' => [
                'comma.csv',
                ',',
                [
                    ['name', 'age', 'city'],
                    ['John', '25', 'New York'],
                    ['Jane', '30', 'London'],
                ],
            ],
            'quoted values' => [
                'quoted.csv',
                ';',
                [
                    ['name', 'description'],
                    ['John Smith', 'Works in "Tech"'],
                    ['Jane Doe', 'Lives in; London'],
                ],
            ],
        ];
    }

    #[Test]
    public function shouldReadWhatWasWritten(): void
    {
        $data = [
            ['column1', 'column2'],
            ['value1', 'value2'],
            ['value3', 'value4'],
        ];

        Csv::write($data, $this->tempDir . '/test.csv');

        $result = Csv::read($this->tempDir . '/test.csv');

        $this->assertSame($data, $result);
    }

    #[Test]
    public function shouldReadSpecialCharacters(): void
    {
        $csvContent = 'column1;column2' . "\n" .
                    '"value;""with;semicolon""";value2' . "\n" .
                    '"value ""with quotes""";value with spaces' . "\n" .
                    'value with newline;"value' . "\n" . 'with' . "\n" . 'newlines"' . "\n";

        file_put_contents($this->tempDir . '/test.csv', $csvContent);

        $expectedData = [
            ['column1', 'column2'],
            ['value;"with;semicolon"', 'value2'],
            ['value "with quotes"', 'value with spaces'],
            ['value with newline', "value\nwith\nnewlines"],
        ];

        $result = Csv::read($this->tempDir . '/test.csv');

        $this->assertSame($expectedData, $result);
    }

    #[Test]
    public function shouldReadEmptyFields(): void
    {
        $csvContent = 'column1;column2' . "\n" .
                    'value1;' . "\n" .
                    ';value2' . "\n";

        file_put_contents($this->tempDir . '/test.csv', $csvContent);

        $expectedData = [
            ['column1', 'column2'],
            ['value1', ''],
            ['', 'value2'],
        ];

        $result = Csv::read($this->tempDir . '/test.csv');

        $this->assertSame($expectedData, $result);
    }

    #[Test]
    public function shouldFailOnInvalidFile(): void
    {
        $this->expectException(RuntimeException::class);
        Csv::read('nonexistent.csv');
    }

    #[Test]
    public function shouldGenerateCsvContent(): void
    {
        $data = [
            ['name' => 'John', 'age' => '25'],
            ['name' => 'Jane', 'age' => '30'],
        ];

        $result = Csv::generate($data);
        $expected = "name;age\nJohn;25\nJane;30\n";

        // Normalize line endings.
        $result = str_replace("\r\n", "\n", $result);

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function shouldGenerateCsvFromRows(): void
    {
        $data = [
            ['column1', 'column2'],
            ['value1', 'value2'],
            ['value3', 'value4'],
        ];

        $result = Csv::generate($data);

        $expectedOutput = 'column1;column2' . "\n" .
                          'value1;value2' . "\n" .
                          'value3;value4' . "\n";

        $this->assertSame($expectedOutput, $result);
    }

    #[Test]
    public function shouldGenerateSpecialCharactersEscaped(): void
    {
        $data = [
            ['column1', 'column2'],
            ['value;"with;semicolon"', 'value2'],
            ['value "with quotes"', 'value with spaces'],
            ['value with newline', "value\nwith\nnewlines"],
        ];

        $result = Csv::generate($data, ';', '"');

        $expectedOutput = 'column1;column2' . "\n" .
                        '"value;""with;semicolon""";value2' . "\n" .
                        '"value ""with quotes""";"value with spaces"' . "\n" .
                        '"value with newline";"value' . "\n" . 'with' . "\n" . 'newlines"' . "\n";

        $this->assertSame($expectedOutput, $result);
    }

    #[Test]
    public function shouldGenerateEmptyFields(): void
    {
        $data = [
            ['column1', 'column2'],
            ['value1', ''],
            ['', 'value2'],
        ];

        $result = Csv::generate($data, ';', '"');

        $expectedOutput = 'column1;column2' . "\n" .
                        'value1;' . "\n" .
                        ';value2' . "\n";

        $this->assertSame($expectedOutput, $result);
    }

    #[Test]
    public function shouldGenerateEmptyStringFromEmptyData(): void
    {
        $result = Csv::generate([]);
        $this->assertSame('', $result);
    }

    #[Test]
    public function shouldRoundTripSpecialCharacters(): void
    {
        $data = [
            ['name' => 'John; Smith', 'note' => 'Contains; semicolon'],
            ['name' => 'Jane "Doe"', 'note' => 'Contains "quotes"'],
        ];

        $csv = Csv::generate($data);
        $result = Csv::load($csv);
        $expected = array_merge(
            [array_keys($data[0])],
            array_map('array_values', $data)
        );
        $this->assertSame($expected, $result);
    }

    #[Test]
    public function shouldWriteCsvFile(): void
    {
        $data = [
            ['name' => 'John', 'age' => '25'],
            ['name' => 'Jane', 'age' => '30'],
        ];

        $outputFile = $this->tempDir . '/output.csv';
        Csv::write($data, $outputFile);

        $this->assertFileExists($outputFile);
        $expected = array_merge(
            [array_keys($data[0])],
            array_map('array_values', $data)
        );
        $this->assertSame($expected, Csv::read($outputFile));
    }

    #[Test]
    public function shouldFailOnUnwritableLocation(): void
    {
        $this->expectException(LogicException::class);

        $data = [['test' => 'value']];
        $invalidPath = '/invalid/path/file.csv';

        Csv::write($data, $invalidPath);
    }

    #[Test]
    public function shouldSendCsvToOutput(): void
    {
        $data = [
            ['name' => 'John', 'age' => '25'],
            ['name' => 'Jane', 'age' => '30'],
        ];

        // Start output buffering.
        ob_start();

        Csv::send($data, 'test.csv', sendHeaders: false);

        $output = ob_get_clean();

        // Verify content.
        $result = Csv::load($output);
        $expected = array_merge(
            [array_keys($data[0])],
            array_map('array_values', $data)
        );
        $this->assertSame($expected, $result);
    }

    #[Test]
    public function shouldValidateAFileThatLooksLikeACsv(): void
    {
        $this->assertTrue(Csv::validate($this->fixturesDir . '/standard.csv'));
    }

    #[Test]
    public function shouldNotValidateAFileThatDoesNotExistOrIsEmpty(): void
    {
        $this->assertFalse(Csv::validate($this->tempDir . '/missing.csv'));

        file_put_contents($this->tempDir . '/empty.csv', '');
        $this->assertFalse(Csv::validate($this->tempDir . '/empty.csv'));
    }

    #[Test]
    public function shouldNotValidateAFileWithADifferentNumberOfColumnsInItsRows(): void
    {
        file_put_contents($this->tempDir . '/ragged.csv', "a;b\n1;2;3\n");

        $this->assertFalse(Csv::validate($this->tempDir . '/ragged.csv'));
    }

    #[Test]
    public function shouldOnlyLookAtTheFirstRowsThatTheSampleSays(): void
    {
        file_put_contents($this->tempDir . '/late.csv', "a;b\n1;2\n3;4\n5;6;7\n");

        $this->assertFalse(Csv::validate($this->tempDir . '/late.csv'));
        $this->assertTrue(Csv::validate($this->tempDir . '/late.csv', sampleSize: 3));
    }

    #[Test]
    public function shouldValidateWithTheSeparatorThatIsGiven(): void
    {
        file_put_contents($this->tempDir . '/comma.csv', "a,b\n1,2\n");

        $this->assertTrue(Csv::validate($this->tempDir . '/comma.csv', ','));
    }

    #[Test]
    public function shouldValidateWithoutUsingAnyDeprecatedFunctionOfTheLibrary(): void
    {
        // The library tells about a deprecated function once in each process,
        // so it is asked in a process of its own.
        $script = $this->tempDir . '/validate.php';
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . ';
$deprecations = [];
set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
    $deprecations[] = $message;
    return true;
}, E_DEPRECATED | E_USER_DEPRECATED);
$valid = Derafu\Support\Csv::validate(' . var_export($this->fixturesDir . '/standard.csv', true) . ');
echo json_encode(["valid" => $valid, "deprecations" => $deprecations]);
');

        $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');

        $this->assertSame('{"valid":true,"deprecations":[]}', $output);
    }
}
