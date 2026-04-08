<?php
namespace App\Utils;
use Illuminate\Support\Facades\DB;
class ConditionUtils {

    public static function check($conditonList, $questionList, $codeConvertor = null)
    {
        $errorList = [];
        $defaultTypeMap = [
            'sample_no'      => 'integer not null',
            'm_agency'       => 'character varying(8) not null',
            'm_id'           => 'character varying(512) not null',
            'm_sex'          => 'integer',
            'm_age'          => 'integer',
            'm_pref'         => 'integer',
            'm_job'          => 'integer',
            'm_marriage'     => 'integer',
            // 'm_login_pw'     => 'character varying(512)',
            // 'm_navigate_id'  => 'character varying(512)',
            'quota1'         => 'character varying(2048)',
            'quota2'         => 'character varying(2048)',
            'quota3'         => 'character varying(2048)',
            'other_data01'   => 'character varying(2048)',
            'other_data02'   => 'character varying(2048)',
            'other_data03'   => 'character varying(2048)',
            'other_data04'   => 'character varying(2048)',
            'other_data05'   => 'character varying(2048)',
            'other_data06'   => 'character varying(2048)',
            'other_data07'   => 'character varying(2048)',
            'other_data08'   => 'character varying(2048)',
            'other_data09'   => 'character varying(2048)',
            'other_data10'   => 'character varying(2048)',
            // 'env_ip'         => 'character varying(128)',
            'env_blows'      => 'character varying(512)',
            'state'          => 'character varying(8)',
            // 'page'           => 'character varying(64)',
            // 'data_mode'      => 'character varying(2)',
            // 'start_part_no'  => 'integer',
            // 'before_part_no' => 'integer',
            // 'next_part_no'   => 'integer',
            // 'start_date'     => 'timestamp(6) without time zone',
            // 'update_date'    => 'timestamp(6) without time zone',
            // 'panel_connect'  => 'character varying(2048)',
        ];//config('common.CONDITION_ATTRIBUTE_COL') and quota1-3 と一致を保つ

        if($codeConvertor === null){
            $codeConvertor = new CodeConvertorUtils($questionList);
        }
        $conditionSqlList = [];
        foreach ($conditonList as $condition) {
            $sql = $codeConvertor->convert($condition);
            if($sql === false){
                $errorList[$condition] = [
                    'sql' => $sql
                ];
                continue;
            }
            $conditionSqlList[$condition] = $sql;
        }
        if(empty($conditionSqlList)) return $errorList;//全条件式エラー

        $sql_combine = '(' . implode(') and (', $conditionSqlList) . ')';
        $createColumn = [];
        $attrCols = config('common.CONDITION_ATTRIBUTE_COL');
        $attrDummyCols = array_flip(array_filter($attrCols, function($key){return !is_numeric($key);}, ARRAY_FILTER_USE_KEY));
        foreach ($questionList as $question) {
            $col = trim($question['qCol'] ?? '');
            if ($col === '') continue;

            if(isset($attrDummyCols[$col])){
                $col = $attrDummyCols[$col];
            }
            elseif (strpos($sql_combine, $col) === false) {
                // 条件式に含まれないカラムは除外
                continue;
            }

            // typeが指定されている場合
            switch ($question['type']) {
                case 'SA':
                    $createColumn[] = "$col integer";
                    break;
                case 'MA':
                    $createColumn[] = "$col character varying(1)";
                    foreach ($question['categories'] as $category) {
                        foreach ($category['otherFa'] as $sort => $otherFa) {
                            $colname = "{$col}_snt{$category['catNo']}_" . ($sort + 1);
                            $createColumn[] = "$colname character varying(1)";
                        }
                    }
                    break;
                case 'FA':
                case 'NU':
                    foreach ($question['categories'] as $category) {
                        $colname = "{$col}_{$category['catNo']}";
                        $createColumn[] = "$colname character varying(1)";
                    }
                    break;
                default:
                    // デフォルトは文字列型
                    $createColumn[] = "$col " . ($defaultTypeMap[$col] ?? 'character varying(255)');
                    break;
            }
        }
        $createColumn = array_unique($createColumn);

        if(empty($createColumn)){
            foreach ($conditionSqlList as $condition => $sql) {
                $errorList[$condition] =  [
                    'sql' => $sql
                ];
            }
            return $errorList;
        }

        // 一時テーブル作成
        $tableName = 'condition_check' . uniqid();
        DB::statement('CREATE TEMPORARY TABLE ' . $tableName . ' (' . implode(',', $createColumn) . ')');

        foreach ($conditionSqlList as $condition => $sql) {
            try {
                DB::statement('SELECT 1 FROM ' . $tableName . ' WHERE ' . $sql);
            } catch (\Exception $e) {
                $errorList[$condition] =  [
                    'sql' => $sql
                ];
            }
        }

        return $errorList;
    }
}
