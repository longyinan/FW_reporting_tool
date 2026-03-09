<?php

namespace App\Models\DB90;

use App\Utils\LegacyPostgresSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RsAnsData extends Model
{
    protected $connection = 'db90';

    private const MAX_ANSTABLE_COUNT = 20;// 回答Tableの最大数

    protected $existsTableList = [];

    protected $columnsTableMap = [];

    private ?LegacyPostgresSchema $legacySchema = null;

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

    public function countByQuestion(int $ank_id, array $partsNoList, string $qCol, string $type, array $catNos): array
    {
        $countMap = [];
        foreach ($catNos as $catNo) {
            $countMap[(int) $catNo] = 0;
        }

        foreach ($partsNoList as $partNo) {
            $tableList = $this->getExistTableList($ank_id, (int) $partNo);
            foreach ($tableList as $tableName) {
                if (!$this->legacySchema()->hasColumn($tableName, $qCol)) {
                    continue;
                }

                if ($type === 'SA') {
                    $rows = DB::connection($this->connection)->table($tableName)
                        ->selectRaw(sprintf('%s as answer_value, COUNT(*) as row_count', $qCol))
                        ->whereIn($qCol, $catNos)
                        ->groupBy($qCol)
                        ->get();

                    foreach ($rows as $row) {
                        $catNo = (int) $row->answer_value;
                        $countMap[$catNo] = (int) ($countMap[$catNo] ?? 0) + (int) $row->row_count;
                    }

                    break 2;
                }

                $rows = DB::connection($this->connection)->table($tableName)
                    ->selectRaw(sprintf('%s as answer_value, COUNT(*) as row_count', $qCol))
                    ->whereNotNull($qCol)
                    ->where($qCol, '<>', '')
                    ->groupBy($qCol)
                    ->get();

                foreach ($rows as $row) {
                    $rawValue = (string) $row->answer_value;
                    $rowCount = (int) $row->row_count;
                    $binary = str_split($rawValue);
                    foreach ($binary as $index => $bit) {
                        if ((string) $bit !== '1') {
                            continue;
                        }
                        $catNo = $catNos[$index] ?? ($index + 1);
                        $countMap[$catNo] = (int) ($countMap[$catNo] ?? 0) + $rowCount;
                    }
                }
                break 2;
            }
        }

        return $countMap;
    }

    private function legacySchema(): LegacyPostgresSchema
    {
        if ($this->legacySchema === null) {
            $this->legacySchema = new LegacyPostgresSchema($this->connection);
        }

        return $this->legacySchema;
    }
}
