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
                $catNos,
                $question['filter'] ?? null
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
        $targetColumn = (string) ($data['target_column'] ?? '');
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 10);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $targetColumn)) {
            return [
                'items' => [],
                'pagination' => [
                    'page' => max($page, 1),
                    'per_page' => max($perPage, 1),
                    'total' => 0,
                ],
            ];
        }

        $enquete = $this->prjInfo->get($ank_id);
        $enquete->load(['eqtInfos:nxs_ank_book_seq,nxs_enquete_no']);
        $partsNoList = $enquete->eqtInfos->pluck('nxs_enquete_no')->values()->all();

        $result = $this->rsAnsData->getFaGraphData(
            $ank_id,
            $partsNoList,
            $targetColumn,
            $page,
            $perPage,
            $data['filter'] ?? null
        );

        return [
            'items' => $result['items'],
            'pagination' => $result['pagination'],
        ];
    }

    public function showCross(int $ank_id, string $sideQno, string $headQno, ?array $filter = null){
        $questionList = $this->enqueteService->getQuestionList($ank_id);
        $sideQuestion = $this->findQuestionByQno($questionList, $sideQno);
        $headQuestion = $this->findQuestionByQno($questionList, $headQno);
        if ($sideQuestion === null || $headQuestion === null) {
            return [];
        }

        $enquete = $this->prjInfo->get($ank_id);
        $enquete->load(['eqtInfos:nxs_ank_book_seq,nxs_enquete_no']);
        $partsNoList = $enquete->eqtInfos->pluck('nxs_enquete_no')->values()->all();

        $sideQuestions = !empty($sideQuestion['subQuestions']) ? $sideQuestion['subQuestions'] : [$sideQuestion];
        $headQuestions = !empty($headQuestion['subQuestions']) ? $headQuestion['subQuestions'] : [$headQuestion];
        $sideGroupName = !empty($sideQuestion['subQuestions']) ? ($sideQuestion['name'] ?? null) : null;
        $headGroupName = !empty($headQuestion['subQuestions']) ? ($headQuestion['name'] ?? null) : null;
        $crossTables = [];

        foreach ($sideQuestions as $sideItem) {
            foreach ($headQuestions as $headItem) {
                $sideCatNos = collect($sideItem['categories'] ?? [])->pluck('catNo')->map(fn ($catNo) => (int) $catNo)->values()->all();
                $headCatNos = collect($headItem['categories'] ?? [])->pluck('catNo')->map(fn ($catNo) => (int) $catNo)->values()->all();

                if (($sideItem['qCol'] ?? null) === ($headItem['qCol'] ?? null)) {
                    continue;
                }

                $crossData = $this->rsAnsData->getCrossCountMatrix(
                    $ank_id,
                    $partsNoList,
                    (string) ($sideItem['qCol'] ?? ''),
                    (string) ($sideItem['type'] ?? ''),
                    $sideCatNos,
                    (string) ($headItem['qCol'] ?? ''),
                    (string) ($headItem['type'] ?? ''),
                    $headCatNos,
                    $filter
                );

                $rows = [];
                $headCatNames = [];
                foreach (($headItem['categories'] ?? []) as $headCategory) {
                    $headCatNames[] = [
                        'catNo' => (int) ($headCategory['catNo'] ?? 0),
                        'name' => $headCategory['name'] ?? null,
                    ];
                }

                foreach (($sideItem['categories'] ?? []) as $sideCategory) {
                    $sideCatNo = (int) ($sideCategory['catNo'] ?? 0);
                    $rowTotal = (int) ($crossData['row_totals'][$sideCatNo] ?? 0);
                    $cells = [];
                    foreach (($headItem['categories'] ?? []) as $headCategory) {
                        $headCatNo = (int) ($headCategory['catNo'] ?? 0);
                        $count = (int) ($crossData['matrix'][$sideCatNo][$headCatNo] ?? 0);
                        $cells[] = [
                            'catNo' => $headCatNo,
                            'count' => $count,
                            'rate' => $rowTotal > 0 ? round(($count / $rowTotal) * 100, 1) : 0,
                        ];
                    }

                    $rows[] = [
                        'catNo' => $sideCatNo,
                        'name' => $sideCategory['name'] ?? null,
                        'count' => $rowTotal,
                        'rate' => $rowTotal > 0 ? 100.0 : 0,
                        'cells' => $cells,
                    ];
                }

                $crossTable = [
                    'sideQCol' => $sideItem['qCol'] ?? null,
                    'sideName' => $sideItem['name'] ?? null,
                    'sideGroupName' => $sideGroupName,
                    'headQCol' => $headItem['qCol'] ?? null,
                    'headName' => $headItem['name'] ?? null,
                    'headGroupName' => $headGroupName,
                    'headCatNames' => $headCatNames,
                    'total' => (int) ($crossData['total'] ?? 0),
                    'rows' => $rows,
                ];

                $crossTables[] = $crossTable;
            }
        }

        return $crossTables;
    }

    private function findQuestionByQno(array $questionList, string $qNo): ?array
    {
        foreach ($questionList as $question) {
            if (($question['qNo'] ?? null) === $qNo && in_array(strtoupper((string) ($question['type'] ?? '')), ['SA', 'MA'], true)) {
                return $question;
            }

            if (!empty($question['subQuestions']) && is_array($question['subQuestions'])) {
                if (($question['qNo'] ?? null) === $qNo) {
                    $subQuestions = array_values(array_filter(
                        $question['subQuestions'],
                        fn (array $subQuestion) => in_array(strtoupper((string) ($subQuestion['type'] ?? '')), ['SA', 'MA'], true)
                    ));

                    if (!empty($subQuestions)) {
                        $question['subQuestions'] = $subQuestions;
                        return $question;
                    }
                }

                foreach ($question['subQuestions'] as $subQuestion) {
                    if (($subQuestion['qNo'] ?? null) === $qNo && in_array(strtoupper((string) ($subQuestion['type'] ?? '')), ['SA', 'MA'], true)) {
                        return $subQuestion;
                    }
                }
            }
        }

        return null;
    }
}
