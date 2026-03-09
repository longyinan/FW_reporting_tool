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
            $this->makeQuestion('sc1', 'SC1', 'あなたの性別をお選びください。', 'SA'),
        ]);
        $prjInfo->shouldReceive('get')->once()->with(100)->andReturn($enquete);
        $qtpQuotaTable->shouldReceive('getList')->once()->with(100)->andReturn($quotaList);
        $rsAttribute->shouldNotReceive('countByQuotaValue');

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute, $rsAnsData, $enqueteService);
        $result = $service->index(100);

        $this->assertSame([
            $this->makeQuestion('sc1', 'SC1', 'あなたの性別をお選びください。', 'SA'),
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
            $this->makeQuestion('sc1', 'SC1', 'あなたの性別をお選びください。', 'SA'),
            $this->makeQuestion('sc2', 'SC2', 'あなたの年齢をお知らせください。', 'NU'),
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
            $this->makeQuestion('sc1', 'SC1', 'あなたの性別をお選びください。', 'SA'),
            $this->makeQuestion('sc2', 'SC2', 'あなたの年齢をお知らせください。', 'NU'),
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
            $this->makeQuestion('scx', 'SCX', 'あなたの居住地をお知らせください。', 'SA'),
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
            $this->makeQuestion('scx', 'SCX', 'あなたの居住地をお知らせください。', 'SA'),
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

        $this->assertSame('SC1', $result['qNo']);
        $this->assertSame(4, $result['total']);
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

        $this->assertSame('QG1', $result['qNo']);
        $this->assertCount(2, $result['subQuestions']);
        $this->assertSame('QG1_1', $result['subQuestions'][0]['qNo']);
        $this->assertSame(50.0, $result['subQuestions'][0]['categories'][0]['rate']);
        $this->assertSame('QG1_2', $result['subQuestions'][1]['qNo']);
        $this->assertSame(75.0, $result['subQuestions'][1]['categories'][0]['rate']);
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
