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

    public function countByQuestion(
        int $ank_id,
        array $partsNoList,
        string $qCol,
        string $type,
        array $catNos,
        ?array $filter = null
    ): array {
        $countMap = [];
        foreach ($catNos as $catNo) {
            $countMap[(int) $catNo] = 0;
        }

        $tableName = $this->findTableByColumn($ank_id, $partsNoList, $qCol);
        if ($tableName === null) {
            return $countMap;
        }

        $query = DB::connection($this->connection)->table(sprintf('%s as target_table', $tableName));

        $qColExpr = sprintf('target_table.%s', $qCol);
        $this->applyFilterConstraint($query, $ank_id, $partsNoList, $tableName, $filter);

        if ($type === 'SA') {
            $rows = (clone $query)
                ->selectRaw(sprintf('%s as answer_value, COUNT(*) as row_count', $qColExpr))
                ->whereIn($qColExpr, $catNos)
                ->groupByRaw($qColExpr)
                ->get();

            foreach ($rows as $row) {
                $catNo = (int) $row->answer_value;
                $countMap[$catNo] = (int) ($countMap[$catNo] ?? 0) + (int) $row->row_count;
            }

            return $countMap;
        }

        $rows = (clone $query)
            ->selectRaw(sprintf('%s as answer_value, COUNT(*) as row_count', $qColExpr))
            ->whereNotNull($qColExpr)
            ->where($qColExpr, '<>', '')
            ->groupByRaw($qColExpr)
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
        int $perPage = 10,
        ?array $filter = null
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

        $baseQuery = DB::connection($this->connection)->table(sprintf('%s as target_table', $tableName))
            ->selectRaw(sprintf('target_table.sample_no as sample_no, target_table.%s as answer_value', $targetColumn))
            ->whereNotNull(sprintf('target_table.%s', $targetColumn))
            ->orderBy('target_table.sample_no')
            ;
        $this->applyFilterConstraint($baseQuery, $ank_id, $partsNoList, $tableName, $filter);
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
        array $headCatNos,
        ?array $filter = null
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

        $filterTable = null;
        $filterCol = null;
        $filterValue = null;
        if ($filter !== null) {
            $filterCol = (string) $filter['colname'];
            $filterValue = (int) $filter['value'];
            $filterTable = $this->findTableByColumn($ank_id, $partsNoList, $filterCol);
        }

        $answerPairs = $this->getCrossAnswerPairs(
            $sideTable,
            $sideQCol,
            $headTable,
            $headQCol,
            $filterTable,
            $filterCol,
            $filterValue
        );
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

    private function getCrossAnswerPairs(
        string $sideTable,
        string $sideQCol,
        string $headTable,
        string $headQCol,
        ?string $filterTable = null,
        ?string $filterCol = null,
        ?int $filterValue = null
    ): array
    {
        $builder = DB::connection($this->connection)->table(sprintf('%s as side_table', $sideTable));
        $sampleNoColumn = 'side_table.sample_no';
        $headValueExpr = sprintf('side_table.%s', $headQCol);

        if ($sideTable !== $headTable) {
            $builder->join(sprintf('%s as head_table', $headTable), 'side_table.sample_no', '=', 'head_table.sample_no');
            $headValueExpr = sprintf('head_table.%s', $headQCol);
        }

        if ($filterTable !== null && $filterCol !== null && $filterValue !== null) {
            if ($filterTable === $sideTable) {
                $builder->where(sprintf('side_table.%s', $filterCol), '=', $filterValue);
            } elseif ($filterTable === $headTable && $sideTable !== $headTable) {
                $builder->where(sprintf('head_table.%s', $filterCol), '=', $filterValue);
            } else {
                $builder->join(
                    sprintf('%s as filter_table', $filterTable),
                    'side_table.sample_no',
                    '=',
                    'filter_table.sample_no'
                )->where(sprintf('filter_table.%s', $filterCol), '=', $filterValue);
            }
        }

        return $builder
            ->selectRaw(sprintf('side_table.%s as side_value, %s as head_value', $sideQCol, $headValueExpr))
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

    private function applyFilterConstraint($query, int $ank_id, array $partsNoList, string $targetTable, ?array $filter): void
    {
        if ($filter === null) {
            return;
        }

        $filterCol = (string) $filter['colname'];
        $filterTable = $this->findTableByColumn($ank_id, $partsNoList, $filterCol);
        if ($filterTable === null) {
            return;
        }

        $filterValue = (int) $filter['value'];

        if ($filterTable === $targetTable) {
            $query->where(sprintf('target_table.%s', $filterCol), '=', $filterValue);
            return;
        }

        $query->join(
            sprintf('%s as filter_table', $filterTable),
            'target_table.sample_no',
            '=',
            'filter_table.sample_no'
        )->where(sprintf('filter_table.%s', $filterCol), '=', $filterValue);
    }
    public function findSample(
        int $ank_id,
        array $partsNoList,
         $sampleNos='',
        int $sort = 1,
        array $conditionRes = [],
        int $page = 1,
        int $per_page = 20

    ): array {
        $tableList = [];
        $joinIndex = 1;

        foreach ($partsNoList as $partNo) {
            $tableList[] = $this->getExistTableList($ank_id, (int)$partNo);
            $mainTable = "rs_attribute_{$ank_id}_{$partNo}";
        }

        $baseQuery = DB::connection($this->connection)
            ->table($mainTable . ' AS main')
            ->selectRaw('main.sample_no, to_char(main.update_date, \'YYYY-MM-DD HH24:MI:SS\') as update_date');

        foreach ($tableList as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $alias = 'rs_' . $joinIndex++;

                $baseQuery->join(
                    DB::raw($v1 . ' AS ' . $alias),
                    $alias . '.sample_no',
                    '=',
                    'main.sample_no'
                );
            }
        }
        if($sampleNos){
            $arrSamp = explode(",", $sampleNos);
            $baseQuery->whereIn('main.sample_no', $arrSamp);
        }
        foreach ($conditionRes as $condition) {
            $baseQuery->whereRaw('(' . $condition . ')');
        }
        if ($sort == 1) {
            $baseQuery->orderBy('main.sample_no', 'asc');
        } else {
            $baseQuery->orderBy('main.sample_no', 'desc');
        }
        $total = (clone $baseQuery)->count();
        $rows = $baseQuery->forPage($page, $per_page)->get();
        if($rows){
            return [
                'items' => $rows->map(fn ($row) => [
                    'sample_no' => $row->sample_no,
                    'update_date' => $row->update_date,
                ])->toArray(),
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                ],
            ];
        }else{
            return [
                'items' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => 0,
                ],
            ];
        }

    }

    public function findSampleOne(
        int $ank_id,
        array $partsNoList,
        $sampleNos='',
    ): array {
        $tableList = [];
        $joinIndex = 1;

        foreach ($partsNoList as $partNo) {
            $tableList[] = $this->getExistTableList($ank_id, (int)$partNo);
            $mainTable = "rs_attribute_{$ank_id}_{$partNo}";
        }

        $baseQuery = DB::connection($this->connection)
            ->table($mainTable . ' AS main')
            ->selectRaw('*');

        foreach ($tableList as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $alias = 'rs_' . $joinIndex++;

                $baseQuery->join(
                    DB::raw($v1 . ' AS ' . $alias),
                    $alias . '.sample_no',
                    '=',
                    'main.sample_no'
                );
            }
        }
        $baseQuery->where('main.sample_no', $sampleNos);

        $rows = $baseQuery->first();
        return $rows ? (array)$rows : [];

    }
}
