<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Turso\Driver\Laravel\Database\LibSQLConnection;
use Turso\Driver\Laravel\Database\LibSQLDatabase;

/**
 * NOTE:
 * All commented providers still in discussion, if you have any cool idea
 * you can adjust this test and make it more robust. Thxy
 */
class LibSQLConnectionTest extends TestCase
{
    private LibSQLConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $mockDatabase = $this->createMock(LibSQLDatabase::class);
        $this->connection = new LibSQLConnection($mockDatabase);
    }

    protected function tearDown(): void
    {
        unset($this->connection);
        parent::tearDown();
    }

    #[DataProvider(methodName: 'escapeStringProvider')]
    public function test_escape_string($string, $expected): void
    {
        $result = $this->connection->escapeString($string);
        $this->assertEquals($expected, $result);
    }

    public static function escapeStringProvider(): array
    {
        return [
            'null value' => [null, 'NULL'],
            'empty string' => ['', ''],
            'simple string' => ['hello', 'hello'],
            'string with single quote' => ["O'Reilly", "O''Reilly"],
            'string with double quote' => ['Say "Hello"', 'Say "Hello"'],
            'string with backslash' => ['C:\\path\\to\\file', 'C:\\path\\to\\file'],
            'string with newline' => ["Line1\nLine2", "Line1\nLine2"],
            'string with carriage return' => ["Line1\rLine2", "Line1\rLine2"],
            'string with null byte' => ["Null\x00Byte", "Null\x00Byte"],
            'string with substitute character' => ["Sub\x1aChar", "Sub\x1aChar"],
            // 'complex string' => ["It's a \"complex\" string\nWith multiple 'special' chars\x00\r\n", "It's a \"complex\" string\nWith multiple 'special' chars\x00\r\n"],
        ];
    }

    #[DataProvider(methodName: 'quoteProvider')]
    public function test_quote($string, $expected): void
    {
        $result = $this->connection->quote($string);
        $this->assertEquals($expected, $result);
    }

    public static function quoteProvider(): array
    {
        return [
            'empty string' => ['', "''"],
            'simple string' => ['hello', "'hello'"],
            'string with single quote' => ["O'Reilly", "'O''Reilly'"],
            'string with double quote' => ['Say "Hello"', "'Say \"Hello\"'"],
            'string with backslash' => ['C:\\path\\to\\file', "'C:\\path\\to\\file'"],
            'string with newline' => ["Line1\nLine2", "'Line1\nLine2'"],
            'string with carriage return' => ["Line1\rLine2", "'Line1\rLine2'"],
            'string with null byte' => ["Null\x00Byte", "'Null\x00Byte'"],
            'string with substitute character' => ["Sub\x1aChar", "'Sub\x1aChar'"],
            'multi-byte characters' => ['こんにちは', "'こんにちは'"],
            'very long string' => [str_repeat('a', 1000000), "'".str_repeat('a', 1000000)."'"],
        ];
    }

    #[DataProvider(methodName: 'sqlInjectionEncodingProvider')]
    public function test_sql_injection_via_encoding($input, $expected)
    {
        $result = $this->connection->escapeString($input);
        $this->assertEquals($expected, $result);

        $this->assertFalse(strpos($result, "';") !== false, 'Potential SQL injection point found');
    }

    public static function sqlInjectionEncodingProvider(): array
    {
        return [
            'Single Quote' => [
                "OR '1'='1;",
                "OR ''1''=''1;",
            ],
            'Double Quote' => [
                'OR "1"="1;',
                'OR "1"="1;',
            ],
            // 'Latin1 to UTF-8 Single Quote' => [
            //     utf8_encode("’") . ";",
            //     "’;",
            // ],
            'Unicode Code Points' => [
                "' OR 1=1--;",
                "'' OR 1=1--;",
            ],
            // 'URL Encoded Quotes' => [
            //     "' %27 OR '1'='1;",
            //     "%27 %27 OR %271%27=%271;",
            // ],
            'Multi-byte Character Before Quote' => [
                "中文'OR'1'='1;",
                "中文''OR''1''=''1;",
            ],
            'Null Byte Injection' => [
                "admin\0' OR '1'='1;",
                "admin\0'' OR ''1''=''1;",
            ],
            'Unicode Normalization' => [
                "¼' OR '1'='1;",
                "¼'' OR ''1''=''1;",
            ],
            'Emoji with Single Quote' => [
                "🔥' OR '1'='1;",
                "🔥'' OR ''1''=''1;",
            ],
        ];
    }
}
