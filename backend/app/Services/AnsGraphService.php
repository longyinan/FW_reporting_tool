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
        $enquete->load(['eqtInfos:nxs_ank_book_seq,nxs_enquete_no']);
        $partsNoList = $enquete->eqtInfos->pluck('nxs_enquete_no')->values();

        $quotaList = $this->qtpQuotaTable->getList($ank_id);
        $quotaList->load(['cellInfos:quota_table_id,quota_cell_name,quota_value,num_target_samples,num_collected_samples,num_cut_collected_samples']);

        if ($partsNoList->count() < 2) {
            return [
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
            'questionList' => $questionList,
            'quotaList' => $quotaList
        ];
    }

    public function showGraph($ank_id, $question)
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
                    'qNo' => $targetQuestion['qNo'] ?? null,
                    'type' => $type,
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
                $category['count'] = $count;
                $category['rate'] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                $graphCategories[] = $category;
            }

            $graphList[] = [
                'qCol' => $qCol,
                'qNo' => $targetQuestion['qNo'] ?? null,
                'name' => $targetQuestion['name'] ?? null,
                'type' => $type,
                'total' => $total,
                'categories' => $graphCategories,
            ];
        }

        if ($hasSubQuestions) {
            return [
                'qNo' => $question['qNo'] ?? null,
                'name' => $question['name'] ?? null,
                'type' => strtoupper((string) ($question['type'] ?? '')),
                'subQuestions' => $graphList,
            ];
        }

        return $graphList[0] ?? [
            'qCol' => null,
            'qNo' => $question['qNo'] ?? null,
            'type' => strtoupper((string) ($question['type'] ?? '')),
            'total' => 0,
            'categories' => [],
        ];
    }
}
