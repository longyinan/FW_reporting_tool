<?php

namespace Tests\Unit\Models\DB90;

use App\Models\DB90\RsAnsData;
use App\Utils\LegacyPostgresSchema;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class RsAnsDataTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_count_by_question_counts_single_select_values(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_10_1_1')->andReturn($builder);
        $builder->shouldReceive('selectRaw')->once()->with('sc1 as answer_value, COUNT(*) as row_count')->andReturnSelf();
        $builder->shouldReceive('whereIn')->once()->with('sc1', [1, 2])->andReturnSelf();
        $builder->shouldReceive('groupBy')->once()->with('sc1')->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['answer_value' => 1, 'row_count' => 3],
            (object) ['answer_value' => 2, 'row_count' => 1],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_10_1_1', 'sc1')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->once()->with(10, 1)->andReturn(['rs_ansdata_10_1_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->countByQuestion(10, [1], 'sc1', 'SA', [1, 2]);

        $this->assertSame([
            1 => 3,
            2 => 1,
        ], $result);
    }

    public function test_count_by_question_counts_multi_select_bits(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_20_2_1')->andReturn($builder);
        $builder->shouldReceive('selectRaw')->once()->with('qg1_2 as answer_value, COUNT(*) as row_count')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->once()->with('qg1_2')->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('qg1_2', '<>', '')->andReturnSelf();
        $builder->shouldReceive('groupBy')->once()->with('qg1_2')->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['answer_value' => '10', 'row_count' => 2],
            (object) ['answer_value' => '01', 'row_count' => 1],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_20_2_1', 'qg1_2')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->once()->with(20, 2)->andReturn(['rs_ansdata_20_2_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->countByQuestion(20, [2], 'qg1_2', 'MA', [1, 2]);

        $this->assertSame([
            1 => 2,
            2 => 1,
        ], $result);
    }

    public function test_get_cross_count_matrix_counts_same_table_intersections(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_30_1_1')->andReturn($builder);
        $builder->shouldReceive('selectRaw')->once()->with('f2 as side_value, f1 as head_value')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->once()->with('sample_no')->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['side_value' => 1, 'head_value' => 1],
            (object) ['side_value' => 1, 'head_value' => 2],
            (object) ['side_value' => 2, 'head_value' => 2],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_30_1_1', 'f2')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_30_1_1', 'f1')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->twice()->andReturn(['rs_ansdata_30_1_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->getCrossCountMatrix(30, [1], 'f2', 'SA', [1, 2], 'f1', 'SA', [1, 2]);

        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['row_totals'][1]);
        $this->assertSame(1, $result['matrix'][1][1]);
        $this->assertSame(1, $result['matrix'][1][2]);
        $this->assertSame(1, $result['matrix'][2][2]);
    }

    public function test_get_cross_count_matrix_joins_head_table_when_tables_differ(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_40_1_1 as side_table')->andReturn($builder);
        $builder->shouldReceive('join')->once()->with(
            'rs_ansdata_40_2_1 as head_table',
            'side_table.sample_no',
            '=',
            'head_table.sample_no'
        )->andReturnSelf();
        $builder->shouldReceive('selectRaw')->once()->with(
            'f2 as side_value, f1 as head_value'
        )->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->once()->with('side_table.sample_no')->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['side_value' => 1, 'head_value' => 2],
            (object) ['side_value' => 2, 'head_value' => 1],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_40_1_1', 'f2')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_40_1_1', 'f1')->andReturn(false);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_40_2_1', 'f1')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->with(40, 1)->andReturn(['rs_ansdata_40_1_1']);
        $model->shouldReceive('getExistTableList')->with(40, 2)->andReturn(['rs_ansdata_40_2_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->getCrossCountMatrix(40, [1, 2], 'f2', 'SA', [1, 2], 'f1', 'SA', [1, 2]);

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['matrix'][1][2]);
        $this->assertSame(1, $result['matrix'][2][1]);
    }

    private function injectLegacySchema(RsAnsData $model, LegacyPostgresSchema $legacySchema): void
    {
        $property = new ReflectionProperty(RsAnsData::class, 'legacySchema');
        $property->setAccessible(true);
        $property->setValue($model, $legacySchema);
    }
}
