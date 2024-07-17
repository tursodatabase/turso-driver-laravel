<?php

namespace Turso\Driver\Laravel\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Turso\Driver\Laravel\Database\LibSQLConnection;
use Turso\Driver\Laravel\Database\LibSQLDatabase;

class ConnectionsSetupTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        $dbFile = getcwd().'/tests/_files/test.db';
        if (File::exists($dbFile)) {
            File::delete($dbFile);
        }
        parent::tearDown();
    }

    public function testConnectionInMemory(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.libsql', [
            'driver' => 'libsql',
            'url' => ':memory:', // Taken from README docs
            'authToken' => '', // This should be defied even with memory, which is not right
            'syncUrl' => '', // This should be defied even with memory, which is not right
            'encryptionKey' => '', // This should be defied even with memory, which is not right
            'remoteOnly' => false, // This should be defied even with memory, which is not right
            'prefix' => '',
            'database' => null, // doesn't matter actually, since we use sqlite
        ]);
        // Get the default connection
        $connection = DB::connection('libsql');

        // Assert that the connection is an instance of LibSQLConnection
        $this->assertInstanceOf(LibSQLConnection::class, $connection);

        // Get the PDO instance
        /**
         * @var LibSQLConnection $connection
         */
        $pdo = $connection->getPdo();

        $this->assertInstanceOf(LibSQLDatabase::class, $pdo);
        $this->assertEquals('local', $pdo->getDb()->mode);
        $this->assertEquals('memory', $pdo->getConnectionMode());
        // we get "local" since our path is file:/turso-driver-laravel/vendor/orchestra/testbench-core/laravel/database/:memory:
        // which is also wrong since we want just to use memory according to README.md
        $result = $connection->select('PRAGMA database_list');
        $this->assertNotEmpty($result);
        $result = $pdo->query('SELECT sqlite_version()');
        $this->assertNotEmpty($result, 'Failed to query SQLite version');
    }

    public function testConnectionLocalFile(): void
    {

        $dbFile = getcwd().'/tests/_files/test.db';
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.libsql', [
            'driver' => 'libsql',
            'url' => "file:$dbFile",
            'authToken' => '',
            'syncUrl' => '',
            'syncInterval' => 5,
            'readYourWrites' => true,
            'encryptionKey' => '',
            'remoteOnly' => false,
            'prefix' => '',
            'database' => null, // doesn't matter actually, since we use sqlite
        ]);

        $connection = DB::connection('libsql');

        // Assert that the connection is an instance of LibSQLConnection
        $this->assertInstanceOf(LibSQLConnection::class, $connection);

        // Get the PDO instance
        /**
         * @var LibSQLConnection $connection
         */
        $pdo = $connection->getPdo();

        $this->assertInstanceOf(LibSQLDatabase::class, $pdo);
        $this->assertEquals('local', $pdo->getDb()->mode);
        $this->assertEquals('local', $pdo->getConnectionMode());
        $result = $connection->select('PRAGMA database_list');
        $this->assertNotEmpty($result);
        $result = $pdo->query('SELECT sqlite_version()');
        $this->assertNotEmpty($result, 'Failed to query SQLite version');

        $this->assertTrue(File::exists($dbFile), 'No file created or wrong path');
    }
}
