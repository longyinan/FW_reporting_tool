<?php

namespace App\Models\DB90;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class RsAttribute extends Model
{
    protected $connection = 'db90';

    public function getTableName($ank_id, $part_no){
       return sprintf("rs_attribute_%d_%d", $ank_id, $part_no);
    }

    public function getModel($ank_id, $part_no){
        $tableName = $this->getTableName($ank_id, $part_no);
        if(Schema::connection('db90')->hasTable($tableName)){
            return DB::connection('db90')->table($tableName);
        }else{
            throw new Exception($tableName . ' not exists');
        }
    }

    /**
     * quota_value_type を問わず、まず DB 側で quotaParam を group 集計し、
     * MA(1) の場合のみ PHP 側でビット展開して quota_value ごとの件数へ再集計する。
     */
    public function countByQuotaValue(int $ankId, int $partNo, string $quotaParam, int $quotaValueType, array $cellValues): array
    {
        $builder = $this->getModel($ankId, $partNo)
            ->selectRaw(sprintf('%s as raw_quota_value, COUNT(*) as row_count', $quotaParam))
            ->groupBy($quotaParam);

        if ($quotaValueType === 0) {
            $builder->whereIn($quotaParam, $cellValues);
        } else {
            $builder
                ->whereNotNull($quotaParam)
                ->where($quotaParam, '<>', '');
        }

        $result = $builder->get();

        if ($quotaValueType === 0) {
            $countMap = [];
            foreach ($result as $row) {
                $countMap[(int) $row->raw_quota_value] = (int) $row->row_count;
            }

            return $countMap;
        }

        $countMap = [];
        foreach ($cellValues as $cellValue) {
            $countMap[(int) $cellValue] = 0;
        }

        foreach ($result as $row) {
            $rawValue = (string) $row->raw_quota_value;
            $rowCount = (int) $row->row_count;
            $binary = str_split($rawValue);

            foreach ($binary as $index => $bit) {
                if ((string) $bit !== '1') {
                    continue;
                }

                $mappedQuotaValue = $cellValues[$index] ?? ($index + 1);
                $mappedQuotaValue = (int) $mappedQuotaValue;
                $countMap[$mappedQuotaValue] = (int) ($countMap[$mappedQuotaValue] ?? 0) + $rowCount;
            }
        }

        return $countMap;
    }
}
