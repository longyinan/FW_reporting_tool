<?php

namespace Tests\Unit\Services;

use App\Models\DB10\PrjInfo;
use App\Models\DB10\QtpQuotaTable;
use App\Models\DB90\RsAnsData;
use App\Models\DB90\RsAttribute;
use App\Services\AnsGraphService;
use App\Services\EnqueteService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class AnsGraphServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_quota_list_without_count_when_parts_is_less_than_two(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);
        $rsAnsData = Mockery::mock(RsAnsData::class);
        $enqueteService = Mockery::mock(EnqueteService::class);

        $enquete = new FakeEnquete([1]);
        $quotaList = new FakeQuotaList([
            $this->makeQuotaTable('quota1', 0, [1, 2]),
        ]);

        $enqueteService->shouldReceive('getQuestionList')->once()->with(100)->andReturn([
            $this->makeQuestion('sc1', 'SC1', 'Q1', 'SA'),
        ]);
        $prjInfo->shouldReceive('get')->once()->with(100)->andReturn($enquete);
        $qtpQuotaTable->shouldReceive('getList')->once()->with(100)->andReturn($quotaList);
        $rsAttribute->shouldNotReceive('countByQuotaValue');

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $result = $service->index(100);

        $this->assertSame([
            'clean_flag' => null,
            'nxs_ank_name' => null,
            'nxs_enquete_name' => null,
        ], $result['enquete']);

        $this->assertSame([
            $this->makeQuestion('sc1', 'SC1', 'Q1', 'SA'),
        ], $result['questionList']);
        $this->assertSame($quotaList, $result['quotaList']);
        $this->assertFalse(isset($result['quotaList'][0]->cellInfos[0]->sc_count));
        $this->assertFalse(isset($result['quotaList'][0]->cellInfos[0]->hon_count));
        $this->assertFalse(isset($result['quotaList'][0]->cellInfos[0]->appearance_rate));
    }

    public function test_index_counts_single_select_by_database_aggregation(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);
        $rsAnsData = Mockery::mock(RsAnsData::class);
        $enqueteService = Mockery::mock(EnqueteService::class);

        $enquete = new FakeEnquete([1, 9]);
        $quotaList = new FakeQuotaList([
            $this->makeQuotaTable('quota1', 0, [1, 2, 3]),
        ]);

        $enqueteService->shouldReceive('getQuestionList')->once()->with(200)->andReturn([
            $this->makeQuestion('sc1', 'SC1', 'Q1', 'SA'),
            $this->makeQuestion('sc2', 'SC2', 'Q2', 'NU'),
        ]);
        $prjInfo->shouldReceive('get')->once()->with(200)->andReturn($enquete);
        $qtpQuotaTable->shouldReceive('getList')->once()->with(200)->andReturn($quotaList);
        $rsAttribute->shouldReceive('countByQuotaValue')->once()->with(
            200,
            1,
            'quota1',
            0,
            [1, 2, 3]
        )->andReturn([
            1 => 10,
            2 => 4,
        ]);
        $rsAttribute->shouldReceive('countByQuotaValue')->once()->with(
            200,
            9,
            'quota1',
            0,
            [1, 2, 3]
        )->andReturn([
            1 => 5,
            3 => 2,
        ]);

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $result = $service->index(200);

        $this->assertSame([
            'clean_flag' => null,
            'nxs_ank_name' => null,
            'nxs_enquete_name' => null,
        ], $result['enquete']);

        $this->assertSame([
            $this->makeQuestion('sc1', 'SC1', 'Q1', 'SA'),
            $this->makeQuestion('sc2', 'SC2', 'Q2', 'NU'),
        ], $result['questionList']);
        $cell1 = $result['quotaList'][0]->cellInfos[0];
        $cell2 = $result['quotaList'][0]->cellInfos[1];
        $cell3 = $result['quotaList'][0]->cellInfos[2];

        $this->assertSame(10, $cell1->sc_count);
        $this->assertSame(5, $cell1->hon_count);
        $this->assertSame(50.0, $cell1->appearance_rate);

        $this->assertSame(4, $cell2->sc_count);
        $this->assertSame(0, $cell2->hon_count);
        $this->assertSame(0.0, $cell2->appearance_rate);

        $this->assertSame(0, $cell3->sc_count);
        $this->assertSame(2, $cell3->hon_count);
        $this->assertNull($cell3->appearance_rate);
    }

    public function test_index_counts_multi_select_from_bit_string(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);
        $rsAnsData = Mockery::mock(RsAnsData::class);
        $enqueteService = Mockery::mock(EnqueteService::class);

        $enquete = new FakeEnquete([3, 8]);
        $quotaList = new FakeQuotaList([
            $this->makeQuotaTable('quota2', 1, [1, 2, 3]),
        ]);

        $enqueteService->shouldReceive('getQuestionList')->once()->with(300)->andReturn([
            $this->makeQuestion('scx', 'SCX', 'QX', 'SA'),
        ]);
        $prjInfo->shouldReceive('get')->once()->with(300)->andReturn($enquete);
        $qtpQuotaTable->shouldReceive('getList')->once()->with(300)->andReturn($quotaList);
        $rsAttribute->shouldReceive('countByQuotaValue')->once()->with(
            300,
            3,
            'quota2',
            1,
            [1, 2, 3]
        )->andReturn([
            1 => 2,
            2 => 0,
            3 => 1,
        ]);
        $rsAttribute->shouldReceive('countByQuotaValue')->once()->with(
            300,
            8,
            'quota2',
            1,
            [1, 2, 3]
        )->andReturn([
            1 => 1,
            2 => 0,
            3 => 2,
        ]);

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $result = $service->index(300);

        $this->assertSame([
            'clean_flag' => null,
            'nxs_ank_name' => null,
            'nxs_enquete_name' => null,
        ], $result['enquete']);

        $this->assertSame([
            $this->makeQuestion('scx', 'SCX', 'QX', 'SA'),
        ], $result['questionList']);
        $cell1 = $result['quotaList'][0]->cellInfos[0];
        $cell2 = $result['quotaList'][0]->cellInfos[1];
        $cell3 = $result['quotaList'][0]->cellInfos[2];

        $this->assertSame(2, $cell1->sc_count);
        $this->assertSame(1, $cell1->hon_count);
        $this->assertSame(50.0, $cell1->appearance_rate);

        $this->assertSame(0, $cell2->sc_count);
        $this->assertSame(0, $cell2->hon_count);
        $this->assertNull($cell2->appearance_rate);

        $this->assertSame(1, $cell3->sc_count);
        $this->assertSame(2, $cell3->hon_count);
        $this->assertSame(200.0, $cell3->appearance_rate);
    }

    public function test_show_graph_returns_single_question_graph_with_one_decimal_rate(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);
        $rsAnsData = Mockery::mock(RsAnsData::class);
        $enqueteService = Mockery::mock(EnqueteService::class);

        $prjInfo->shouldReceive('get')->once()->with(400)->andReturn(new FakeEnquete([1, 2]));
        $rsAnsData->shouldReceive('countByQuestion')->once()->with(
            400,
            [1, 2],
            'sc1',
            'SA',
            [1, 2]
        )->andReturn([
            1 => 3,
            2 => 1,
        ]);

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $result = $service->showGraph(400, $this->makeGraphQuestion('sc1', 'SC1', 'SA', [1, 2]));

        $this->assertSame('sc1', $result['qCol']);
        $this->assertSame(4, $result['total']);
        $this->assertSame(1, $result['categories'][0]['catNo']);
        $this->assertSame(75.0, $result['categories'][0]['rate']);
        $this->assertSame(25.0, $result['categories'][1]['rate']);
    }

    public function test_show_graph_handles_sub_questions(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);
        $rsAnsData = Mockery::mock(RsAnsData::class);
        $enqueteService = Mockery::mock(EnqueteService::class);

        $prjInfo->shouldReceive('get')->once()->with(500)->andReturn(new FakeEnquete([3]));
        $rsAnsData->shouldReceive('countByQuestion')->once()->with(
            500,
            [3],
            'qg1_1',
            'SA',
            [1, 2]
        )->andReturn([
            1 => 2,
            2 => 2,
        ]);
        $rsAnsData->shouldReceive('countByQuestion')->once()->with(
            500,
            [3],
            'qg1_2',
            'MA',
            [1, 2]
        )->andReturn([
            1 => 3,
            2 => 1,
        ]);

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $question = [
            'qNo' => 'QG1',
            'name' => 'group',
            'type' => 'QG',
            'subQuestions' => [
                $this->makeGraphQuestion('qg1_1', 'QG1_1', 'SA', [1, 2]),
                $this->makeGraphQuestion('qg1_2', 'QG1_2', 'MA', [1, 2]),
            ],
        ];

        $result = $service->showGraph(500, $question);

        $this->assertCount(2, $result);
        $this->assertSame('qg1_1', $result[0]['qCol']);
        $this->assertSame(50.0, $result[0]['categories'][0]['rate']);
        $this->assertSame('qg1_2', $result[1]['qCol']);
        $this->assertSame(75.0, $result[1]['categories'][0]['rate']);
    }

    public function test_show_fa_graph_returns_first_page_with_default_pagination(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);
        $rsAnsData = Mockery::mock(RsAnsData::class);
        $enqueteService = Mockery::mock(EnqueteService::class);

        $prjInfo->shouldReceive('get')->once()->with(600)->andReturn(new FakeEnquete([1]));
        $rsAnsData->shouldReceive('getFaGraphData')->once()->with(
            600,
            [1],
            'sc1_2',
            1,
            10
        )->andReturn([
            'items' => [
                ['sample_no' => 1001, 'value' => 'text1'],
                ['sample_no' => 1002, 'value' => 'text2'],
            ],
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total' => 32,
            ],
        ]);

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $result = $service->showFaGraph(600, [
            'target_column' => 'sc1_2',
        ]);

        $this->assertArrayNotHasKey('target_column', $result);
        $this->assertArrayNotHasKey('sample_nos', $result);
        $this->assertSame(2, count($result['items']));
        $this->assertSame(32, $result['pagination']['total']);
    }

    public function test_show_fa_graph_returns_requested_page(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);
        $rsAnsData = Mockery::mock(RsAnsData::class);
        $enqueteService = Mockery::mock(EnqueteService::class);

        $prjInfo->shouldReceive('get')->once()->with(700)->andReturn(new FakeEnquete([2]));
        $rsAnsData->shouldReceive('getFaGraphData')->once()->with(
            700,
            [2],
            'qg1_1_1',
            2,
            20
        )->andReturn([
            'items' => [
                ['sample_no' => 2021, 'value' => 'fa-a'],
                ['sample_no' => 2022, 'value' => 'fa-b'],
            ],
            'pagination' => [
                'page' => 2,
                'per_page' => 20,
                'total' => 88,
            ],
        ]);

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $result = $service->showFaGraph(700, [
            'target_column' => 'qg1_1_1',
            'page' => 2,
            'per_page' => 20,
        ]);

        $this->assertArrayNotHasKey('target_column', $result);
        $this->assertArrayNotHasKey('sample_nos', $result);
        $this->assertSame('fa-a', $result['items'][0]['value']);
        $this->assertSame(2, $result['pagination']['page']);
    }

    public function test_show_cross_returns_single_cross_table(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);
        $rsAnsData = Mockery::mock(RsAnsData::class);
        $enqueteService = Mockery::mock(EnqueteService::class);

        $questionList = [
            $this->makeGraphQuestion('f2', 'F2', 'SA', [1, 2]),
            $this->makeGraphQuestion('f1', 'F1', 'SA', [1, 2]),
        ];

        $enqueteService->shouldReceive('getQuestionList')->once()->with(800)->andReturn($questionList);
        $prjInfo->shouldReceive('get')->once()->with(800)->andReturn(new FakeEnquete([1]));
        $rsAnsData->shouldReceive('getCrossCountMatrix')->once()->with(
            800,
            [1],
            'f2',
            'SA',
            [1, 2],
            'f1',
            'SA',
            [1, 2]
        )->andReturn([
            'matrix' => [
                1 => [1 => 33, 2 => 9],
                2 => [1 => 3, 2 => 15],
            ],
            'row_totals' => [
                1 => 42,
                2 => 18,
            ],
            'total' => 60,
        ]);

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $result = $service->showCross(800, 'F2', 'F1');

        $this->assertCount(1, $result);
        $this->assertSame('f2', $result[0]['sideQCol']);
        $this->assertSame('F2', $result[0]['sideName']);
        $this->assertNull($result[0]['sideGroupName']);
        $this->assertSame('f1', $result[0]['headQCol']);
        $this->assertSame('F1', $result[0]['headName']);
        $this->assertNull($result[0]['headGroupName']);
        $this->assertSame('cat1', $result[0]['headCatNames'][0]['name']);
        $this->assertSame(60, $result[0]['total']);
        $this->assertSame(42, $result[0]['rows'][0]['count']);
        $this->assertSame('cat1', $result[0]['rows'][0]['name']);
        $this->assertArrayNotHasKey('name', $result[0]['rows'][0]['cells'][0]);
        $this->assertSame(78.6, $result[0]['rows'][0]['cells'][0]['rate']);
    }

    public function test_show_cross_expands_sub_questions_to_multi_tables(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);
        $rsAnsData = Mockery::mock(RsAnsData::class);
        $enqueteService = Mockery::mock(EnqueteService::class);

        $questionList = [
            [
                'qNo' => 'SQ',
                'name' => 'group-side',
                'subQuestions' => [
                    $this->makeGraphQuestion('sq1', 'SQ1', 'SA', [1, 2]),
                    $this->makeGraphQuestion('sq2', 'SQ2', 'SA', [1, 2]),
                ],
            ],
            [
                'qNo' => 'HQ',
                'name' => 'group-head',
                'subQuestions' => [
                    $this->makeGraphQuestion('hq1', 'HQ1', 'SA', [1, 2]),
                    $this->makeGraphQuestion('hq2', 'HQ2', 'SA', [1, 2]),
                ],
            ],
        ];

        $enqueteService->shouldReceive('getQuestionList')->once()->with(900)->andReturn($questionList);
        $prjInfo->shouldReceive('get')->once()->with(900)->andReturn(new FakeEnquete([2]));

        foreach ([['sq1', 'hq1'], ['sq1', 'hq2'], ['sq2', 'hq1'], ['sq2', 'hq2']] as [$sideQCol, $headQCol]) {
            $rsAnsData->shouldReceive('getCrossCountMatrix')->once()->with(
                900,
                [2],
                $sideQCol,
                'SA',
                [1, 2],
                $headQCol,
                'SA',
                [1, 2]
            )->andReturn([
                'matrix' => [
                    1 => [1 => 1, 2 => 0],
                    2 => [1 => 0, 2 => 1],
                ],
                'row_totals' => [
                    1 => 1,
                    2 => 1,
                ],
                'total' => 2,
            ]);
        }

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $result = $service->showCross(900, 'SQ', 'HQ');

        $this->assertCount(4, $result);
        $this->assertSame('group-side', $result[0]['sideGroupName']);
        $this->assertSame('group-head', $result[0]['headGroupName']);
        $this->assertSame('sq1', $result[0]['sideQCol']);
        $this->assertSame('SQ1', $result[0]['sideName']);
        $this->assertSame('hq1', $result[0]['headQCol']);
        $this->assertSame('HQ1', $result[0]['headName']);
        $this->assertSame('sq2', $result[3]['sideQCol']);
        $this->assertSame('hq2', $result[3]['headQCol']);
    }

    private function makeQuotaTable(string $quotaParam, int $valueType, array $cellValues): object
    {
        $table = new \stdClass();
        $table->quota_param = $quotaParam;
        $table->quota_value_type = $valueType;
        $table->cellInfos = collect(array_map(function ($value) {
            $cell = new \stdClass();
            $cell->quota_value = $value;
            return $cell;
        }, $cellValues));

        return $table;
    }

    private function makeQuestion(string $qCol, string $qNo, string $name, string $type): array
    {
        return [
            'qCol' => $qCol,
            'qNo' => $qNo,
            'name' => $name,
            'type' => $type,
            'categories' => [],
        ];
    }

    private function makeGraphQuestion(string $qCol, string $qNo, string $type, array $catNos): array
    {
        return [
            'qCol' => $qCol,
            'qNo' => $qNo,
            'name' => $qNo,
            'type' => $type,
            'categories' => array_map(
                fn (int $catNo) => ['catNo' => $catNo, 'name' => 'cat' . $catNo],
                $catNos
            ),
        ];
    }
}

class FakeEnquete
{
    public Collection $eqtInfos;

    public function __construct(array $parts)
    {
        $this->eqtInfos = collect(array_map(
            fn (int $partNo) => (object) ['nxs_enquete_no' => $partNo],
            $parts
        ));
    }

    public function load(array $relations): self
    {
        return $this;
    }
}

class FakeQuotaList extends EloquentCollection
{
    public function load($relations)
    {
        return $this;
    }
}


