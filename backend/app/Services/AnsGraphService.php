<?php

namespace App\Services;

use App\Models\DB10\PrjInfo;
use App\Models\DB10\QtpQuotaTable;
use App\Models\DB90\RsAttribute;

class AnsGraphService
{
    public function __construct(
        protected PrjInfo $prjInfo,
        protected QtpQuotaTable $qtpQuotaTable,
        protected RsAttribute $rsAttribute
    ) {}

    public function index(int $id)
    {
        $enquete = $this->prjInfo->get($id);
        $enquete->load(['eqtInfos:nxs_ank_book_seq,nxs_enquete_no']);
        $partsNoList = $enquete->eqtInfos->pluck('nxs_enquete_no')->values();

        $quotaList = $this->qtpQuotaTable->getList($id);
        $quotaList->load(['cellInfos:quota_table_id,quota_cell_name,quota_value,num_target_samples,num_collected_samples,num_cut_collected_samples']);

        if ($partsNoList->count() < 2) {
            return $quotaList;
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
                $id,
                $scPartNo,
                $quotaParam,
                $valueType,
                $cellValues
            );
            $honCountMap = $this->rsAttribute->countByQuotaValue(
                $id,
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
                    ? round(($honCount / $scCount) * 100, 2)
                    : null;
            }
        }

        return $quotaList;
    }
}
