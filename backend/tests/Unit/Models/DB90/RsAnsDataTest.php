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

    public function test_count_by_question_applies_filter_where_on_same_table(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_50_1_1 as target_table')->andReturn($builder);
        $builder->shouldReceive('where')->once()->with('target_table.q1', '=', 2)->andReturnSelf();
        $builder->shouldReceive('selectRaw')->once()->with('target_table.sc1 as answer_value, COUNT(*) as row_count')->andReturnSelf();
        $builder->shouldReceive('whereIn')->once()->with('target_table.sc1', [1, 2])->andReturnSelf();
        $builder->shouldReceive('groupByRaw')->once()->with('target_table.sc1')->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['answer_value' => 1, 'row_count' => 2],
            (object) ['answer_value' => 2, 'row_count' => 1],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_50_1_1', 'sc1')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_50_1_1', 'q1')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->twice()->with(50, 1)->andReturn(['rs_ansdata_50_1_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->countByQuestion(50, [1], 'sc1', 'SA', [1, 2], [
            'colname' => 'q1',
            'value' => 2,
        ]);

        $this->assertSame([
            1 => 2,
            2 => 1,
        ], $result);
    }

    public function test_count_by_question_joins_filter_table_when_tables_differ(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_60_1_1 as target_table')->andReturn($builder);
        $builder->shouldReceive('join')->once()->with(
            'rs_ansdata_60_2_1 as filter_table',
            'target_table.sample_no',
            '=',
            'filter_table.sample_no'
        )->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('filter_table.q99', '=', 7)->andReturnSelf();
        $builder->shouldReceive('selectRaw')->once()->with('target_table.sc1 as answer_value, COUNT(*) as row_count')->andReturnSelf();
        $builder->shouldReceive('whereIn')->once()->with('target_table.sc1', [1, 2])->andReturnSelf();
        $builder->shouldReceive('groupByRaw')->once()->with('target_table.sc1')->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['answer_value' => 1, 'row_count' => 3],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_60_1_1', 'sc1')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_60_1_1', 'q99')->andReturn(false);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_60_2_1', 'q99')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->once()->with(60, 1)->andReturn(['rs_ansdata_60_1_1']);
        $model->shouldReceive('getExistTableList')->once()->with(60, 1)->andReturn(['rs_ansdata_60_1_1']);
        $model->shouldReceive('getExistTableList')->once()->with(60, 2)->andReturn(['rs_ansdata_60_2_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->countByQuestion(60, [1, 2], 'sc1', 'SA', [1, 2], [
            'colname' => 'q99',
            'value' => 7,
        ]);

        $this->assertSame([
            1 => 3,
            2 => 0,
        ], $result);
    }

    public function test_get_cross_count_matrix_counts_same_table_intersections(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_30_1_1 as side_table')->andReturn($builder);
        $builder->shouldReceive('selectRaw')->once()->with('side_table.f2 as side_value, side_table.f1 as head_value')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->once()->with('side_table.sample_no')->andReturnSelf();
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

    public function test_get_fa_graph_data_applies_filter_where_on_same_table(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_70_1_1 as target_table')->andReturn($builder);
        $builder->shouldReceive('selectRaw')->once()->with('target_table.sample_no as sample_no, target_table.sc1_2 as answer_value')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->once()->with('target_table.sc1_2')->andReturnSelf();
        $builder->shouldReceive('orderBy')->once()->with('target_table.sample_no')->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('target_table.q1', '=', 2)->andReturnSelf();
        $builder->shouldReceive('count')->once()->andReturn(2);
        $builder->shouldReceive('forPage')->once()->with(1, 10)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['sample_no' => 1, 'answer_value' => 'abc'],
            (object) ['sample_no' => 2, 'answer_value' => 'def'],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_70_1_1', 'sc1_2')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_70_1_1', 'q1')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->twice()->with(70, 1)->andReturn(['rs_ansdata_70_1_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->getFaGraphData(70, [1], 'sc1_2', 1, 10, [
            'colname' => 'q1',
            'value' => 2,
        ]);

        $this->assertSame(2, $result['pagination']['total']);
        $this->assertSame('abc', $result['items'][0]['value']);
    }

    public function test_get_fa_graph_data_joins_filter_table_when_tables_differ(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_80_1_1 as target_table')->andReturn($builder);
        $builder->shouldReceive('selectRaw')->once()->with('target_table.sample_no as sample_no, target_table.fa1 as answer_value')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->once()->with('target_table.fa1')->andReturnSelf();
        $builder->shouldReceive('orderBy')->once()->with('target_table.sample_no')->andReturnSelf();
        $builder->shouldReceive('join')->once()->with(
            'rs_ansdata_80_2_1 as filter_table',
            'target_table.sample_no',
            '=',
            'filter_table.sample_no'
        )->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('filter_table.q99', '=', 7)->andReturnSelf();
        $builder->shouldReceive('count')->once()->andReturn(1);
        $builder->shouldReceive('forPage')->once()->with(1, 10)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['sample_no' => 9, 'answer_value' => 'joined'],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_80_1_1', 'fa1')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_80_1_1', 'q99')->andReturn(false);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_80_2_1', 'q99')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->once()->with(80, 1)->andReturn(['rs_ansdata_80_1_1']);
        $model->shouldReceive('getExistTableList')->once()->with(80, 1)->andReturn(['rs_ansdata_80_1_1']);
        $model->shouldReceive('getExistTableList')->once()->with(80, 2)->andReturn(['rs_ansdata_80_2_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->getFaGraphData(80, [1, 2], 'fa1', 1, 10, [
            'colname' => 'q99',
            'value' => 7,
        ]);

        $this->assertSame(1, $result['pagination']['total']);
        $this->assertSame('joined', $result['items'][0]['value']);
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
            'side_table.f2 as side_value, head_table.f1 as head_value'
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

    public function test_get_cross_count_matrix_applies_filter_where_on_side_table(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_85_1_1 as side_table')->andReturn($builder);
        $builder->shouldReceive('where')->once()->with('side_table.q1', '=', 2)->andReturnSelf();
        $builder->shouldReceive('selectRaw')->once()->with('side_table.f2 as side_value, side_table.f1 as head_value')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->once()->with('side_table.sample_no')->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['side_value' => 1, 'head_value' => 1],
            (object) ['side_value' => 2, 'head_value' => 2],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_85_1_1', 'f2')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_85_1_1', 'f1')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_85_1_1', 'q1')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->times(3)->with(85, 1)->andReturn(['rs_ansdata_85_1_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->getCrossCountMatrix(
            85,
            [1],
            'f2',
            'SA',
            [1, 2],
            'f1',
            'SA',
            [1, 2],
            ['colname' => 'q1', 'value' => 2]
        );

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['matrix'][1][1]);
        $this->assertSame(1, $result['matrix'][2][2]);
    }

    public function test_get_cross_count_matrix_joins_filter_table_when_filter_table_differs(): void
    {
        $dbFacade = Mockery::mock('alias:Illuminate\Support\Facades\DB');
        $connection = Mockery::mock();
        $builder = Mockery::mock();

        $dbFacade->shouldReceive('connection')->once()->with('db90')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('rs_ansdata_95_1_1 as side_table')->andReturn($builder);
        $builder->shouldReceive('join')->once()->with(
            'rs_ansdata_95_2_1 as filter_table',
            'side_table.sample_no',
            '=',
            'filter_table.sample_no'
        )->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('filter_table.q99', '=', 7)->andReturnSelf();
        $builder->shouldReceive('selectRaw')->once()->with('side_table.f2 as side_value, side_table.f1 as head_value')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->once()->with('side_table.sample_no')->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['side_value' => 1, 'head_value' => 2],
            (object) ['side_value' => 2, 'head_value' => 1],
        ]));

        $legacySchema = Mockery::mock(LegacyPostgresSchema::class);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_95_1_1', 'f2')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_95_1_1', 'f1')->andReturn(true);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_95_1_1', 'q99')->andReturn(false);
        $legacySchema->shouldReceive('hasColumn')->once()->with('rs_ansdata_95_2_1', 'q99')->andReturn(true);

        $model = Mockery::mock(RsAnsData::class)->makePartial();
        $model->shouldReceive('getExistTableList')->times(3)->with(95, 1)->andReturn(['rs_ansdata_95_1_1']);
        $model->shouldReceive('getExistTableList')->once()->with(95, 2)->andReturn(['rs_ansdata_95_2_1']);
        $this->injectLegacySchema($model, $legacySchema);

        $result = $model->getCrossCountMatrix(
            95,
            [1, 2],
            'f2',
            'SA',
            [1, 2],
            'f1',
            'SA',
            [1, 2],
            ['colname' => 'q99', 'value' => 7]
        );

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
