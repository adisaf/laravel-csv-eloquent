<?php

namespace Paymetrust\CsvEloquent\Tests\Unit;

use Illuminate\Support\Collection;
use Mockery;
use VotreOrganisation\CsvEloquent\Builder;
use VotreOrganisation\CsvEloquent\Models\ModelCSV;
use VotreOrganisation\CsvEloquent\Tests\TestCase;

class BuilderTest extends TestCase
{
    /**
     * Instance de modèle pour les tests.
     *
     * @var \Mockery\MockInterface
     */
    protected $modelMock;

    /**
     * Instance de Builder pour les tests.
     *
     * @var \VotreOrganisation\CsvEloquent\Builder
     */
    protected $builder;

    /**
     * Configuration avant chaque test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Créer un mock du modèle CSV
        $this->modelMock = Mockery::mock(ModelCSV::class);
        $this->modelMock->shouldReceive('getCsvFile')->andReturn('test.csv');
        $this->modelMock->shouldReceive('getKeyName')->andReturn('id');
        $this->modelMock->shouldReceive('mapColumnToField')->andReturnUsing(function ($column) {
            return $column;
        });
        $this->modelMock->shouldReceive('usesSoftDeletes')->andReturn(false);
        $this->modelMock->shouldReceive('newInstance')->andReturnUsing(function ($attributes = [], $exists = false) {
            $model = Mockery::mock(ModelCSV::class);
            $model->shouldReceive('setAttribute')->andReturnSelf();

            return $model;
        });
        $this->modelMock->shouldReceive('newCollection')->andReturnUsing(function ($items = []) {
            return new Collection($items);
        });

        // Instancier le Builder
        $this->builder = new Builder($this->csvClientMock);
        $this->builder->setModel($this->modelMock);
    }

    /** @test */
    public function it_builds_where_clause_correctly()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->once()
            ->with('test.csv', [
                'filters' => [
                    'name' => ['$eq' => 'Test'],
                ],
            ])
            ->andReturn(['data' => []]);

        // Act
        $this->builder->where('name', 'Test')->get();

        // Assert - l'assertion est implicite dans les attentes Mockery
    }

    /** @test */
    public function it_builds_complex_where_clauses_correctly()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->once()
            ->with('test.csv', Mockery::on(function ($params) {
                return isset($params['filters']['age']['$gt']) &&
                    $params['filters']['age']['$gt'] === 18 &&
                    isset($params['filters']['status']['$eq']) &&
                    $params['filters']['status']['$eq'] === 'active';
            }))
            ->andReturn(['data' => []]);

        // Act
        $this->builder
            ->where('age', '>', 18)
            ->where('status', 'active')
            ->get();

        // Assert - l'assertion est implicite dans les attentes Mockery
    }

    /** @test */
    public function it_builds_or_where_clauses_correctly()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->once()
            ->with('test.csv', Mockery::on(function ($params) {
                return isset($params['filters']['$or']) &&
                    count($params['filters']['$or']) === 1 &&
                    isset($params['filters']['$or'][0]['status']['$eq']) &&
                    $params['filters']['$or'][0]['status']['$eq'] === 'pending';
            }))
            ->andReturn(['data' => []]);

        // Act
        $this->builder
            ->where('name', 'Test')
            ->orWhere('status', 'pending')
            ->get();

        // Assert - l'assertion est implicite dans les attentes Mockery
    }

    /** @test */
    public function it_builds_order_by_clause_correctly()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->once()
            ->with('test.csv', Mockery::on(function ($params) {
                return $params['sort'] === 'created_at:desc';
            }))
            ->andReturn(['data' => []]);

        // Act
        $this->builder
            ->orderBy('created_at', 'desc')
            ->get();

        // Assert - l'assertion est implicite dans les attentes Mockery
    }

    /** @test */
    public function it_builds_pagination_params_correctly()
    {
        // Arrange
        $this->csvClientMock->shouldReceive('getData')
            ->once()
            ->with('test.csv', Mockery::on(function ($params) {
                return isset($params['pagination']) &&
                    $params['pagination']['page'] === 2 &&
                    $params['pagination']['pageSize'] === 15;
            }))
            ->andReturn(['data' => [], 'meta' => ['pagination' => ['totalRecords' => 100]]]);

        // Act
        $this->builder->forPage(2, 15)->get();

        // Assert - l'assertion est implicite dans les attentes Mockery
    }

    /** @test */
    public function it_processes_api_results_correctly()
    {
        // Arrange
        $apiResponse = [
            'data' => [
                ['id' => 1, 'name' => 'Test 1'],
                ['id' => 2, 'name' => 'Test 2'],
            ],
            'meta' => [
                'pagination' => [
                    'totalRecords' => 2,
                ],
            ],
        ];

        $this->csvClientMock->shouldReceive('getData')
            ->once()
            ->andReturn($apiResponse);

        // Act
        $result = $this->builder->get();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }
}
