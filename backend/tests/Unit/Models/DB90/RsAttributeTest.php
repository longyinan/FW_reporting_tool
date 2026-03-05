<?php

namespace Tests\Unit\Models\DB90;

use App\Models\DB90\RsAttribute;
use Mockery;
use PHPUnit\Framework\TestCase;

class RsAttributeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_count_by_quota_value_single_select_returns_group_count_map(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('selectRaw')->once()->with('quota1 as raw_quota_value, COUNT(*) as row_count')->andReturnSelf();
        $builder->shouldReceive('groupBy')->once()->with('quota1')->andReturnSelf();
        $builder->shouldReceive('whereIn')->once()->with('quota1', [1, 2, 3])->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['raw_quota_value' => 1, 'row_count' => 7],
            (object) ['raw_quota_value' => 3, 'row_count' => 2],
        ]));

        $model = Mockery::mock(RsAttribute::class)->makePartial();
        $model->shouldReceive('getModel')->once()->with(10, 1)->andReturn($builder);

        $countMap = $model->countByQuotaValue(10, 1, 'quota1', 0, [1, 2, 3]);

        $this->assertSame([1 => 7, 3 => 2], $countMap);
    }

    public function test_count_by_quota_value_multi_select_expands_bit_and_accumulates(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('selectRaw')->once()->with('quota2 as raw_quota_value, COUNT(*) as row_count')->andReturnSelf();
        $builder->shouldReceive('groupBy')->once()->with('quota2')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->once()->with('quota2')->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('quota2', '<>', '')->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) ['raw_quota_value' => '101', 'row_count' => 3],
            (object) ['raw_quota_value' => '010', 'row_count' => 2],
        ]));

        $model = Mockery::mock(RsAttribute::class)->makePartial();
        $model->shouldReceive('getModel')->once()->with(10, 2)->andReturn($builder);

        $countMap = $model->countByQuotaValue(10, 2, 'quota2', 1, [1, 2, 3]);

        $this->assertSame([
            1 => 3,
            2 => 2,
            3 => 3,
        ], $countMap);
    }
}

