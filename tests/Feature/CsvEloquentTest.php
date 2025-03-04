<?php

namespace Adisaf\CsvEloquent\Tests\Feature;

use Adisaf\CsvEloquent\Tests\Fixtures\TestModel;
use Adisaf\CsvEloquent\Tests\TestCase;
use Illuminate\Support\Collection;

class CsvEloquentTest extends TestCase
{
    /**
     * Configuration avant chaque test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Définir les réponses du mock pour les tests d'intégration
        $this->csvClientMock->shouldReceive('getSchema')
            ->andReturn([
                'data' => [
                    'schema' => [
                        'id' => ['type' => 'BIGINT', 'has_nulls' => false],
                        'name' => ['type' => 'VARCHAR', 'has_nulls' => false],
                        'age' => ['type' => 'INTEGER', 'has_nulls' => true],
                        'created_at' => ['type' => 'TIMESTAMP', 'has_nulls' => false],
                        'updated_at' => ['type' => 'TIMESTAMP', 'has_nulls' => false],
                        'deleted_at' => ['type' => 'TIMESTAMP', 'has_nulls' => true],
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_retrieves_all_records()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->with('tests.csv', [])
            ->andReturn([
                'data' => [
                    ['id' => 1, 'name' => 'Test 1', 'age' => 25],
                    ['id' => 2, 'name' => 'Test 2', 'age' => 30],
                ],
            ]);

        // Act
        $result = TestModel::all();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_finds_record_by_id()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->andReturn([
                'data' => [
                    ['id' => 1, 'name' => 'Test 1', 'age' => 25],
                ],
            ]);

        // Act
        $model = TestModel::find(1);

        // Assert
        $this->assertNotNull($model);
        $this->assertEquals(1, $model->id);
    }

    /** @test */
    public function it_filters_records_with_where_clause()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->with('tests.csv', \Mockery::on(function ($params) {
                return isset($params['filters']['age']['$gt']) &&
                    $params['filters']['age']['$gt'] === 25;
            }))
            ->andReturn([
                'data' => [
                    ['id' => 2, 'name' => 'Test 2', 'age' => 30],
                ],
            ]);

        // Act
        $result = TestModel::where('age', '>', 25)->get();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals(30, $result->first()->age);
    }

    /** @test */
    public function it_orders_results_correctly()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->with('tests.csv', \Mockery::on(function ($params) {
                return isset($params['sort']) && $params['sort'] === 'age:desc';
            }))
            ->andReturn([
                'data' => [
                    ['id' => 2, 'name' => 'Test 2', 'age' => 30],
                    ['id' => 1, 'name' => 'Test 1', 'age' => 25],
                ],
            ]);

        // Act
        $result = TestModel::orderBy('age', 'desc')->get();

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals(30, $result->first()->age);
    }

    /** @test */
    public function it_paginates_results()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->with('tests.csv', \Mockery::on(function ($params) {
                return isset($params['pagination']) &&
                    $params['pagination']['page'] === 1 &&
                    $params['pagination']['pageSize'] === 2;
            }))
            ->andReturn([
                'data' => [
                    ['id' => 1, 'name' => 'Test 1', 'age' => 25],
                    ['id' => 2, 'name' => 'Test 2', 'age' => 30],
                ],
                'meta' => [
                    'pagination' => [
                        'totalRecords' => 5,
                    ],
                ],
            ]);

        // Act
        $paginator = TestModel::paginate(2);

        // Assert
        $this->assertEquals(2, $paginator->count());
        $this->assertEquals(5, $paginator->total());
        $this->assertEquals(3, $paginator->lastPage());
    }
}
