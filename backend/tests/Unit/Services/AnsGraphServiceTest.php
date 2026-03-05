<?php

namespace Tests\Unit\Services;

use App\Models\DB10\PrjInfo;
use App\Models\DB10\QtpQuotaTable;
use App\Models\DB90\RsAttribute;
use App\Services\AnsGraphService;
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

        $enquete = new FakeEnquete([1]);
        $quotaList = new FakeQuotaList([
            $this->makeQuotaTable('quota1', 0, [1, 2]),
        ]);

        $prjInfo->shouldReceive('get')->once()->with(100)->andReturn($enquete);
        $qtpQuotaTable->shouldReceive('getList')->once()->with(100)->andReturn($quotaList);
        $rsAttribute->shouldNotReceive('countByQuotaValue');

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute);
        $result = $service->index(100);

        $this->assertSame($quotaList, $result);
        $this->assertFalse(isset($result[0]->cellInfos[0]->sc_count));
        $this->assertFalse(isset($result[0]->cellInfos[0]->hon_count));
        $this->assertFalse(isset($result[0]->cellInfos[0]->appearance_rate));
    }

    public function test_index_counts_single_select_by_database_aggregation(): void
    {
        $prjInfo = Mockery::mock(PrjInfo::class);
        $qtpQuotaTable = Mockery::mock(QtpQuotaTable::class);
        $rsAttribute = Mockery::mock(RsAttribute::class);

        $enquete = new FakeEnquete([1, 9]);
        $quotaList = new FakeQuotaList([
            $this->makeQuotaTable('quota1', 0, [1, 2, 3]),
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

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute);
        $result = $service->index(200);

        $cell1 = $result[0]->cellInfos[0];
        $cell2 = $result[0]->cellInfos[1];
        $cell3 = $result[0]->cellInfos[2];

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

        $enquete = new FakeEnquete([3, 8]);
        $quotaList = new FakeQuotaList([
            $this->makeQuotaTable('quota2', 1, [1, 2, 3]),
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

        $service = new AnsGraphService($prjInfo, $qtpQuotaTable, $rsAttribute);
        $result = $service->index(300);

        $cell1 = $result[0]->cellInfos[0];
        $cell2 = $result[0]->cellInfos[1];
        $cell3 = $result[0]->cellInfos[2];

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
