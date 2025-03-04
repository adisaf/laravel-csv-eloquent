<?php

namespace VotreOrganisation\CsvEloquent\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use VotreOrganisation\CsvEloquent\CsvClient;
use VotreOrganisation\CsvEloquent\Exceptions\CsvApiException;
use VotreOrganisation\CsvEloquent\Tests\TestCase;

class CsvClientTest extends TestCase
{
    /**
     * Instance du client pour les tests.
     *
     * @var \VotreOrganisation\CsvEloquent\CsvClient
     */
    protected $client;

    /**
     * Configuration avant chaque test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Utilisons le vrai client au lieu du mock pour ces tests
        $this->client = new CsvClient;
    }

    /** @test */
    public function it_gets_files_from_api()
    {
        // Arrange
        Http::fake([
            'http://test-api.example.com/api/' => Http::response([
                'data' => [
                    ['name' => 'test1.csv', 'size' => 1000],
                    ['name' => 'test2.csv', 'size' => 2000],
                ],
                'meta' => ['count' => 2],
            ], 200),
        ]);

        // Act
        $result = $this->client->getFiles();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
    }

    /** @test */
    public function it_gets_data_from_api()
    {
        // Arrange
        Http::fake([
            'http://test-api.example.com/api/test.csv' => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'Item 1'],
                    ['id' => 2, 'name' => 'Item 2'],
                ],
                'meta' => ['pagination' => ['totalRecords' => 2]],
            ], 200),
        ]);

        // Act
        $result = $this->client->getData('test.csv');

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
    }

    /** @test */
    public function it_gets_schema_from_api()
    {
        // Arrange
        Http::fake([
            'http://test-api.example.com/api/test.csv/schema' => Http::response([
                'data' => [
                    'filename' => 'test.csv',
                    'schema' => [
                        'id' => ['type' => 'BIGINT'],
                        'name' => ['type' => 'VARCHAR'],
                    ],
                ],
            ], 200),
        ]);

        // Act
        $result = $this->client->getSchema('test.csv');

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('schema', $result['data']);
    }

    /** @test */
    public function it_sends_correct_authentication_headers()
    {
        // Arrange
        Http::fake([
            'http://test-api.example.com/api/*' => Http::response(['data' => []], 200),
        ]);

        // Act
        $this->client->getFiles();

        // Assert
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization') &&
                strpos($request->header('Authorization')[0], 'Basic ') === 0;
        });
    }

    /** @test */
    public function it_throws_exception_on_api_error()
    {
        // Arrange
        Http::fake([
            'http://test-api.example.com/api/*' => Http::response(['error' => 'Access denied'], 403),
        ]);

        // Act & Assert
        $this->expectException(CsvApiException::class);
        $this->client->getFiles();
    }

    /** @test */
    public function it_caches_api_responses()
    {
        // Arrange
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(['data' => []]);

        // Act
        $this->client->getFiles();

        // No assertion needed, the expectations on Cache::remember do the job
    }

    /** @test */
    public function it_clears_cache_for_specific_file()
    {
        // Arrange
        Cache::shouldReceive('forget')
            ->once()
            ->with('csv_api_schema_test.csv');

        Cache::shouldReceive('forget')
            ->once()
            ->with('csv_api_data_test.csv_*');

        // Act
        $this->client->clearCache('test.csv');

        // No assertion needed, the expectations on Cache::forget do the job
    }
}
