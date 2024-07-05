<?php

namespace Turso\Driver\Laravel\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LibSQL;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Turso\Driver\Laravel\Database\LibSQLConnection;
use Turso\Driver\Laravel\Database\LibSQLConnector;
use Turso\Driver\Laravel\Database\LibSQLDatabase;
use Turso\Driver\Laravel\Exceptions\ConfigurationIsNotFound;

class ConnectionsSetupTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        if (File::exists('tests/_files/test.db')) {
            File::delete('tests/_files/test.db');
        }
        if (File::exists('tests/_files/database.sqlite')) {
            File::delete('tests/_files/database.sqlite');
        }
        Mockery::close();
        parent::tearDown();
    }

    #[DataProvider('invalidConfigsProvider')]
    public function testInvalidConfig(?string $defaultConnection, array $config, string $error): void
    {
        config()->set('database.default', $defaultConnection);
        config()->set('database.connections.libsql', $config);
        try {
            DB::connection('libsql');
        } catch (\Exception $e) {
            $this->assertEquals($error, $e->getMessage());

            return;
        }
    }

    public static function invalidConfigsProvider(): \Generator
    {
        yield 'url should be set always' => [
            'defaultConnection' => 'libsql',
            'config' => [
                'driver' => 'libsql',
                'url' => '', // empty url
                'authToken' => '',
                'syncUrl' => '',
                'syncInterval' => 5,
                'readYourWrites' => true,
                'encryptionKey' => '',
                'remoteOnly' => false,
                'prefix' => '',
                'database' => null,
            ],
            'error' => 'URL and database not set, please check your configuration',
        ];
        yield 'url should be correct for file' => [
            'defaultConnection' => 'libsql',
            'config' => [
                'driver' => 'libsql',
                'url' => 'file:test', // file config
                'authToken' => '',
                'syncUrl' => '',
                'syncInterval' => 5,
                'readYourWrites' => true,
                'encryptionKey' => '',
                'remoteOnly' => false,
                'prefix' => '',
                'database' => null,
            ],
            'error' => 'Got driver - file, please check your URL and driver config',
        ];
    }

    public function testConnectionInMemory(): void
    {
        config()->set('database.default', 'libsql');
        config()->set('database.connections.libsql', [
            'driver' => 'libsql',
            'url' => 'libsql::memory',
            'authToken' => '',
            'syncUrl' => '',
            'syncInterval' => 5,
            'readYourWrites' => true,
            'encryptionKey' => '',
            'remoteOnly' => false,
            'prefix' => '',
            'database' => null, // doesn't matter actually, since we use sqlite
        ]);
        // Get the default connection
        $connection = DB::connection('libsql');

        // Assert that the connection is an instance of LibSQLConnection
        $this->assertInstanceOf(LibSQLConnection::class, $connection);

        // Get the PDO instance
        $pdo = $connection->getPdo();

        $this->assertInstanceOf(LibSQLDatabase::class, $pdo);
        $this->assertEquals('local', $pdo->getDb()->mode);
        $this->assertEquals('memory', $pdo->getConnectionMode());
        $result = $connection->select('PRAGMA database_list');
        $this->assertNotEmpty($result);
        $result = $pdo->query('SELECT sqlite_version()');
        $this->assertNotEmpty($result, 'Failed to query SQLite version');
    }

    public function testConnectionLocalFile(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.local_file', [
            'driver' => 'libsql',
            'url' => 'libsql::file:tests/_files/test.db',
            'authToken' => '',
            'syncUrl' => '',
            'syncInterval' => 5,
            'readYourWrites' => true,
            'encryptionKey' => '',
            'remoteOnly' => false,
            'prefix' => '',
            'database' => null, // doesn't matter actually, since we use sqlite
        ]);
        $connection = DB::connection('local_file');

        // Assert that the connection is an instance of LibSQLConnection
        $this->assertInstanceOf(LibSQLConnection::class, $connection);

        // Get the PDO instance
        $pdo = $connection->getPdo();

        $this->assertInstanceOf(LibSQLDatabase::class, $pdo);
        $this->assertEquals('local', $pdo->getDb()->mode);
        $this->assertEquals('local', $pdo->getConnectionMode());
        $result = $connection->select('PRAGMA database_list');
        $this->assertNotEmpty($result);
        $result = $pdo->query('SELECT sqlite_version()');
        $this->assertNotEmpty($result, 'Failed to query SQLite version');

        $this->assertTrue(File::exists('tests/_files/test.db'), 'No file created or wrong path');
    }

    public function testConnectionRemoteReplica(): void
    {
        $config = [
            'driver' => 'libsql',
            'url' => 'libsql::file:tests/_files/database.sqlite',
            'authToken' => 'your-database-auth-token-from-turso',
            'syncUrl' => 'your-database-url-from-turso',
            'syncInterval' => 5,
            'readYourWrites' => true,
            'encryptionKey' => '',
            'remoteOnly' => false,
            'prefix' => '',
            'database' => 'database.sqlite',
        ];
        $expectedLibSQLParams = [
            'url' => 'file:tests/_files/database.sqlite',
            'authToken' => $config['authToken'],
            'syncUrl' => $config['syncUrl'],
            'syncInterval' => $config['syncInterval'],
            'read_your_writes' => $config['readYourWrites'],
            'encryptionKey' => $config['encryptionKey'],
        ];
        $constructorConfig = [
            'driver' => 'libsql',
            'authToken' => 'your-database-auth-token-from-turso',
            'syncUrl' => 'your-database-url-from-turso',
            'syncInterval' => 5,
            'readYourWrites' => true,
            'encryptionKey' => '',
            'remoteOnly' => false,
            'prefix' => '',
            'database' => 'file:tests/_files/database.sqlite',
        ];

        $mockLibSQLDatabase = $this->getMockBuilder(LibSQLDatabase::class)
            ->setConstructorArgs([$constructorConfig])
            ->onlyMethods(['createLibSQL'])
            ->getMock();
        $mockLibSQLDatabase->expects($this->once())
            ->method('createLibSQL')
            ->with($this->equalTo($expectedLibSQLParams))
            ->willReturn(new LibSQL($expectedLibSQLParams));

        $mockConnector = $this->createPartialMock(LibSQLConnector::class, ['connect']);
        $mockConnector->expects($this->once())
            ->method('connect')
            ->willReturnCallback(function () use ($mockLibSQLDatabase) {
                $mockLibSQLDatabase->init();

                return $mockLibSQLDatabase;
            });
        app()->instance(LibSQLConnector::class, $mockConnector);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.remote_replica', $config);
        $connection = DB::connection('remote_replica');

        // Assert that the connection is an instance of LibSQLConnection
        $this->assertInstanceOf(LibSQLConnection::class, $connection);

        // Get the PDO instance
        $pdo = $connection->getPdo();

        $this->assertInstanceOf(LibSQLDatabase::class, $pdo);
        $this->assertEquals('remote_replica', $pdo->getConnectionMode());

        $this->assertTrue(File::exists('tests/_files/database.sqlite'), 'No file created or wrong path');
    }

    public function testConnectionRemote(): void
    {
        $config = [
            'driver' => 'libsql',
            'url' => 'libsql::dbname=libsql://database-org.turso.io',
            'authToken' => 'your-database-auth-token-from-turso',
            'remoteOnly' => true,
            'prefix' => '',
        ];
        $expectedLibSQLParams = 'libsql:dbname=libsql://database-org.turso.io;authToken=your-database-auth-token-from-turso';
        $constructorConfig = [
            'driver' => 'libsql',
            'authToken' => 'your-database-auth-token-from-turso',
            'remoteOnly' => true,
            'prefix' => '',
            'database' => 'dbname=libsql://database-org.turso.io',
        ];

        $mockLibSQLDatabase = $this->getMockBuilder(LibSQLDatabase::class)
            ->setConstructorArgs([$constructorConfig])
            ->onlyMethods(['createLibSQL'])
            ->getMock();
        $mockLibSQLDatabase->expects($this->once())
            ->method('createLibSQL')
            ->with($this->equalTo($expectedLibSQLParams))
            ->willReturn(new LibSQL($expectedLibSQLParams));

        $mockConnector = $this->createPartialMock(LibSQLConnector::class, ['connect']);
        $mockConnector->expects($this->once())
            ->method('connect')
            ->willReturnCallback(function () use ($mockLibSQLDatabase) {
                $mockLibSQLDatabase->init();

                return $mockLibSQLDatabase;
            });
        app()->instance(LibSQLConnector::class, $mockConnector);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.remote', $config);
        $connection = DB::connection('remote');

        // Assert that the connection is an instance of LibSQLConnection
        $this->assertInstanceOf(LibSQLConnection::class, $connection);

        // Get the PDO instance
        $pdo = $connection->getPdo();

        $this->assertInstanceOf(LibSQLDatabase::class, $pdo);
        $this->assertEquals('remote', $pdo->getConnectionMode());
    }

    public function testNotFoundConfiguration()
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.libsql', [
            'driver' => 'libsql',
            'url' => 'libsql::dbname=libsql://database-org.turso.io',
            'remoteOnly' => false,
        ]);
        try {
            DB::connection('libsql');
        } catch (ConfigurationIsNotFound $e) {
            $this->assertEquals('Connection not found!', $e->getMessage());

            return;
        }
    }
}
