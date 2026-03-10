<?php

namespace App\Services;

use App\Models\DB10\PrjInfo;
use App\Models\DB10\QtpQuotaTable;
use App\Models\DB90\RsAnsData;
use App\Models\DB90\RsAttribute;

class AnsGraphService
{
    public function __construct(
        protected PrjInfo $prjInfo,
        protected QtpQuotaTable $qtpQuotaTable,
        protected RsAttribute $rsAttribute,
        protected RsAnsData $rsAnsData,
        protected EnqueteService $enqueteService
    ) {}

    public function index(int $ank_id)
    {
        $questionList = $this->enqueteService->getQuestionList($ank_id);

        $enquete = $this->prjInfo->get($ank_id);
        $enqueteInfo = [
            'clean_flag' => $enquete->clean_flag ?? null,
            'nxs_ank_name' => $enquete->nxs_ank_name ?? null,
            'nxs_enquete_name' => $enquete->nxs_enquete_name ?? null,
        ];
        $enquete->load(['eqtInfos:nxs_ank_book_seq,nxs_enquete_no']);
        $partsNoList = $enquete->eqtInfos->pluck('nxs_enquete_no')->values();

        $quotaList = $this->qtpQuotaTable->getList($ank_id);
        $quotaList->load(['cellInfos:quota_table_id,quota_cell_name,quota_value,num_target_samples,num_collected_samples,num_cut_collected_samples']);

        if ($partsNoList->count() < 2) {
            return [
                'enquete' => $enqueteInfo,
                'questionList' => $questionList,
                'quotaList' => $quotaList
            ];
        }

        $scPartNo = (int) $partsNoList->first();
        $honPartNo = (int) $partsNoList->last();

        foreach ($quotaList as $quotaTable) {
            $quotaParam = $quotaTable->quota_param;
            $valueType = (int) $quotaTable->quota_value_type; // 0:SA 1:MA
            $cellValues = $quotaTable->cellInfos
                ->pluck('quota_value')
                ->map(fn ($value) => (int) $value)
                ->values()
                ->all();

            $scCountMap = $this->rsAttribute->countByQuotaValue(
                $ank_id,
                $scPartNo,
                $quotaParam,
                $valueType,
                $cellValues
            );
            $honCountMap = $this->rsAttribute->countByQuotaValue(
                $ank_id,
                $honPartNo,
                $quotaParam,
                $valueType,
                $cellValues
            );

            foreach ($quotaTable->cellInfos as $cellInfo) {
                $quotaValue = (int) $cellInfo->quota_value;
                $scCount = (int) ($scCountMap[$quotaValue] ?? 0);
                $honCount = (int) ($honCountMap[$quotaValue] ?? 0);

                $cellInfo->sc_count = $scCount;
                $cellInfo->hon_count = $honCount;
                $cellInfo->appearance_rate = $scCount > 0
                    ? round(($honCount / $scCount) * 100, 1)
                    : null;
            }
        }

        return [
            'enquete' => $enqueteInfo,
            'questionList' => $questionList,
            'quotaList' => $quotaList
        ];
    }

    public function showGraph(int $ank_id, array $question)
    {
        $enquete = $this->prjInfo->get($ank_id);
        $enquete->load(['eqtInfos:nxs_ank_book_seq,nxs_enquete_no']);
        $partsNoList = $enquete->eqtInfos->pluck('nxs_enquete_no')->values();

        $hasSubQuestions = !empty($question['subQuestions']) && is_array($question['subQuestions']);
        $targetQuestions = $hasSubQuestions ? $question['subQuestions'] : [$question];
        $graphList = [];

        foreach ($targetQuestions as $targetQuestion) {
            $qCol = $targetQuestion['qCol'] ?? null;
            $type = strtoupper((string) ($targetQuestion['type'] ?? ''));
            $categories = $targetQuestion['categories'] ?? [];

            if (!$qCol || !in_array($type, ['SA', 'MA'], true)) {
                $graphList[] = [
                    'qCol' => $qCol,
                    'total' => 0,
                    'categories' => [],
                ];
                continue;
            }

            $catNos = collect($categories)
                ->pluck('catNo')
                ->map(fn ($catNo) => (int) $catNo)
                ->values()
                ->all();

            $countMap = $this->rsAnsData->countByQuestion(
                $ank_id,
                $partsNoList->all(),
                $qCol,
                $type,
                $catNos
            );

            $total = array_sum($countMap);
            $graphCategories = [];
            foreach ($categories as $category) {
                $catNo = (int) ($category['catNo'] ?? 0);
                $count = (int) ($countMap[$catNo] ?? 0);
                $graphCategories[] = [
                    'catNo' => $catNo,
                    'count' => $count,
                    'rate' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                ];
            }

            $graphList[] = [
                'qCol' => $qCol,
                'total' => $total,
                'categories' => $graphCategories,
            ];
        }

        if ($hasSubQuestions) {
            return $graphList;
        }

        return $graphList[0] ?? [
            'qCol' => null,
            'total' => 0,
            'categories' => [],
        ];
    }

    public function showFaGraph(int $ank_id, array $data)
    {
        $sampleNos = $data['sample_nos'] ?? [];
        $targetColumn = (string) ($data['target_column'] ?? '');
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $targetColumn)) {
            return [
                'target_column' => $targetColumn,
                'sample_nos' => $sampleNos,
                'items' => [],
            ];
        }

        $enquete = $this->prjInfo->get($ank_id);
        $enquete->load(['eqtInfos:nxs_ank_book_seq,nxs_enquete_no']);
        $partsNoList = $enquete->eqtInfos->pluck('nxs_enquete_no')->values()->all();

        $result = $this->rsAnsData->getFaGraphData(
            $ank_id,
            $partsNoList,
            $targetColumn,
            $sampleNos
        );

        $response = [
            'target_column' => $targetColumn,
            'items' => $result['items'],
        ];

        if (array_key_exists('sample_nos', $result)) {
            $response['sample_nos'] = $result['sample_nos'];
        }

        return $response;
    }
}
