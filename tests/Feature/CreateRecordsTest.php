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
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.local_file', [
            'driver' => 'libsql',
            'url' => 'file:/tests/_files/test.db',
            'authToken' => '',
            'syncUrl' => '',
            'syncInterval' => 5,
            'readYourWrites' => true,
            'encryptionKey' => '',
            'remoteOnly' => false,
            'prefix' => '',
            'database' => null, // doesn't matter actually, since we use sqlite
        ]);
        Schema::connection('local_file')
            ->dropIfExists('test');
        Schema::connection('local_file')
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
        if (File::exists('tests/_files/test.db')) {
            File::delete('tests/_files/test.db');
        }
        parent::tearDown();
    }

    public function testCreateViaDB(): void
    {
        DB::connection('local_file')
            ->table('test')
            ->delete();

        $id = DB::connection('local_file')
            ->table('test')
            ->insertGetId([
                'text' => 'text',
                'json' => json_encode(['test' => 'test']),
                'string' => 'string',
            ]);
        $this->assertEquals(1, $id);
        // not working, since insertGetId returns false instead of 1

        DB::connection('local_file')
            ->table('test')
            ->insert([
                'text' => 'text2',
                'json' => json_encode(['test2' => 'test']),
                'string' => 'string2',
            ]);


        $data = DB::connection('local_file')
            ->table('test')
            ->select()
            ->get();
        $this->assertEquals(2, count($data));

        $this->assertEquals('text2', $data[1]['text']);
    }

    public function testCreateViaEloquent(): void
    {
        $modelClass = new class extends Model
        {
            protected $connection = 'local_file';

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

        $data = DB::connection('local_file')
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
