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

        $tableName = $this->findTableByColumn($ank_id, $partsNoList, $qCol);
        if ($tableName === null) {
            return $countMap;
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

            return $countMap;
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

        return $countMap;
    }

    public function getFaGraphData(
        int $ank_id,
        array $partsNoList,
        string $targetColumn,
        int $page = 1,
        int $perPage = 10
    ): array {
        $tableName = $this->findTableByColumn($ank_id, $partsNoList, $targetColumn);
        $page = max($page, 1);
        $perPage = max($perPage, 1);

        if ($tableName === null) {
            return [
                'items' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ];
        }

        $baseQuery = DB::connection($this->connection)->table($tableName)
            ->selectRaw(sprintf('sample_no, %s as answer_value', $targetColumn))
            ->whereNotNull('sample_no')
            ->orderBy('sample_no')
            ;
        $total = (clone $baseQuery)->count();
        $rows = $baseQuery->forPage($page, $perPage)->get();

        return [
            'items' => $rows->map(fn ($row) => [
                'sample_no' => $row->sample_no,
                'value' => $row->answer_value,
            ])->toArray(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    public function getCrossCountMatrix(
        int $ank_id,
        array $partsNoList,
        string $sideQCol,
        string $sideType,
        array $sideCatNos,
        string $headQCol,
        string $headType,
        array $headCatNos
    ): array {
        $matrix = [];
        $rowTotals = [];
        foreach ($sideCatNos as $sideCatNo) {
            $sideCatNo = (int) $sideCatNo;
            $rowTotals[$sideCatNo] = 0;
            foreach ($headCatNos as $headCatNo) {
                $matrix[$sideCatNo][(int) $headCatNo] = 0;
            }
        }

        $sideTable = $this->findTableByColumn($ank_id, $partsNoList, $sideQCol);
        $headTable = $this->findTableByColumn($ank_id, $partsNoList, $headQCol);
        if ($sideTable === null || $headTable === null) {
            return [
                'matrix' => $matrix,
                'row_totals' => $rowTotals,
                'total' => 0,
            ];
        }

        $answerPairs = $this->getCrossAnswerPairs($sideTable, $sideQCol, $headTable, $headQCol);
        foreach ($answerPairs as $answerPair) {
            $sideSelected = $this->extractSelectedCatNos($answerPair['side_value'] ?? null, $sideType, $sideCatNos);
            $headSelected = $this->extractSelectedCatNos($answerPair['head_value'] ?? null, $headType, $headCatNos);
            if (empty($sideSelected) || empty($headSelected)) {
                continue;
            }

            foreach ($sideSelected as $sideCatNo) {
                foreach ($headSelected as $headCatNo) {
                    $matrix[$sideCatNo][$headCatNo] = (int) ($matrix[$sideCatNo][$headCatNo] ?? 0) + 1;
                    $rowTotals[$sideCatNo] = (int) ($rowTotals[$sideCatNo] ?? 0) + 1;
                }
            }
        }

        return [
            'matrix' => $matrix,
            'row_totals' => $rowTotals,
            'total' => array_sum($rowTotals),
        ];
    }

    private function findTableByColumn(int $ank_id, array $partsNoList, string $targetColumn): ?string
    {
        foreach ($partsNoList as $partNo) {
            $tableList = $this->getExistTableList($ank_id, (int) $partNo);
            foreach ($tableList as $tableName) {
                if ($this->legacySchema()->hasColumn($tableName, $targetColumn)) {
                    return $tableName;
                }
            }
        }

        return null;
    }

    private function getCrossAnswerPairs(string $sideTable, string $sideQCol, string $headTable, string $headQCol): array
    {
        if ($sideTable === $headTable) {
            $builder = DB::connection($this->connection)->table($sideTable);
            $sampleNoColumn = 'sample_no';
        } else {
            $builder = DB::connection($this->connection)->table(sprintf('%s as side_table', $sideTable))
                ->join(sprintf('%s as head_table', $headTable), 'side_table.sample_no', '=', 'head_table.sample_no');
            $sampleNoColumn = 'side_table.sample_no';
        }

        return $builder
            ->selectRaw(sprintf('%s as side_value, %s as head_value', $sideQCol, $headQCol))
            ->whereNotNull($sampleNoColumn)
            ->get()
            ->map(fn ($row) => [
                'side_value' => $row->side_value,
                'head_value' => $row->head_value,
            ])
            ->toArray();
    }

    private function extractSelectedCatNos($rawValue, string $type, array $catNos): array
    {
        if ($rawValue === null || $rawValue === '') {
            return [];
        }

        if (strtoupper($type) === 'SA') {
            $selected = (int) $rawValue;
            return in_array($selected, $catNos, true) ? [$selected] : [];
        }

        $selectedCatNos = [];
        $binary = str_split((string) $rawValue);
        foreach ($binary as $index => $bit) {
            if ((string) $bit !== '1') {
                continue;
            }

            $selectedCatNos[] = (int) ($catNos[$index] ?? ($index + 1));
        }

        return $selectedCatNos;
    }

    private function legacySchema(): LegacyPostgresSchema
    {
        if ($this->legacySchema === null) {
            $this->legacySchema = new LegacyPostgresSchema($this->connection);
        }

        return $this->legacySchema;
    }
}
