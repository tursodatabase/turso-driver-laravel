<?php

namespace Turso\Driver\Laravel\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class CreateRecordsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
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
        Schema::connection('libsql')
            ->dropIfExists('test');
        Schema::connection('libsql')
            ->create('test', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->text('text');
                $table->json('json');
                $table->string('string');

                $table->timestamps();
            });
    }

    public function tearDown(): void
    {
        $dbFile = getcwd().'/tests/_files/test.db';
        if (File::exists($dbFile)) {
            File::delete($dbFile);
        }
        parent::tearDown();
    }

    public function testCreateViaDB(): void
    {
        DB::connection('libsql')
            ->table('test')
            ->delete();

        $id = DB::connection('libsql')
            ->table('test')
            ->insertGetId([
                'text' => 'text',
                'json' => json_encode(['test' => 'test']),
                'string' => 'string',
            ]);
        $this->assertEquals(1, $id);
        // not working, since insertGetId returns false instead of 1

        DB::connection('libsql')
            ->table('test')
            ->insert([
                'text' => 'text2',
                'json' => json_encode(['test2' => 'test']),
                'string' => 'string2',
            ]);

        $data = DB::connection('libsql')
            ->table('test')
            ->select()
            ->get();
        $this->assertEquals(2, count($data));

        $this->assertEquals('text2', $data[1]['text']);
    }

    public function testWithBLOBType(): void
    {
        $modelClass = new class extends Model
        {
            protected $connection = 'libsql';

            protected $table = 'test';

            protected $fillable = ['text'];

            public function casts()
            {
                return [
                    'json' => 'array',
                ];
            }
        };

        $model = new $modelClass(['text' => str_repeat('{SOME TEST CONTENT HERE!}', 2)]);
        $model->json = ['test' => 'test'];
        $model->string = 'string';

        $model->save();

        $data = DB::connection('libsql')
            ->table('test')
            ->select()
            ->get()
            ->first();

        $this->assertEquals(str_repeat('{SOME TEST CONTENT HERE!}', 2), $data['text']);
    }

    public function testCreateViaEloquent(): void
    {
        $modelClass = new class extends Model
        {
            protected $connection = 'libsql';

            protected $table = 'test';

            protected $fillable = ['text'];

            public function casts()
            {
                return [
                    'json' => 'array',
                ];
            }
        };

        $model = new $modelClass(['text' => 'test']);
        $model->json = ['test' => 'test'];
        $model->string = 'string';

        $model->save();
        $this->assertEquals(1, $model->id); // not working since insertGetId is not working
        $model->refresh();

        $this->assertNotEmpty($model->created_at);

        $data = DB::connection('libsql')
            ->table('test')
            ->select()
            ->get();
        $this->assertEquals(1, count($data));

        // testing that grammar is correct and json is working
        $result = $model::query()
            ->where(function ($query) {
                $query->where('json->test', 'test');
            })
            ->first();
        $this->assertEquals(1, $result->id);
    }
}
