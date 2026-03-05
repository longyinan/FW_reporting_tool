<?php

namespace App\Models\DB90;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RsAnsData extends Model
{
    protected $connection = 'db90';

    private const MAX_ANSTABLE_COUNT = 20;// 回答Tableの最大数

    protected $existsTableList = [];

    protected $columnsTableMap = [];

    private function getCheckTableList(int $ank_id, int $part_no): array{
        $checkTableList = [];
        for($i=1; $i <= self::MAX_ANSTABLE_COUNT; $i++){
            $checkTableList[] = sprintf("rs_ansdata_%d_%d_%d", $ank_id, $part_no, $i);
        }

        return $checkTableList;
    }

    public function getExistTableList(int $ank_id, int $part_no): array{
        $list = DB::connection($this->connection)->table('pg_class')->select('relname')->whereIn('relname', $this->getCheckTableList($ank_id, $part_no))->get()->toArray();

        return array_column($list, 'relname');
    }
}
