<?php
namespace App\Services;

use App\Models\DB10\EqtInfo;
use Illuminate\Support\Facades\Cache;
use App\Utils\EqtXmlUtil;
class EnqueteService
{
    /**
     * 案件xmlデータを取得
     *
     * @param int $ank_id
     * @param array $parts_no
     * @param array $option
     * @return array
     */
    public function getXmlData($ank_id, array $parts_no = [], array $option = []){
        $EqtInfo = new EqtInfo();
        $eqtInfos = $EqtInfo->getInfos($ank_id, $parts_no);

        $questionList = [];
        $logicList = [];
        foreach($eqtInfos as $info){
            $cacheKey = "eqt_xml_data:{$info->nxs_enquete_no}:{$info->last_upd_ver_id}";
            $eqtXmlData = Cache::rememberForever($cacheKey, function () use ($info){
                $xml = $info->eqtXml;
                $xml_utils = new EqtXmlUtil($xml->structure_xml,$xml->logic_xml);

                $allQuestion = $xml_utils->getAllQuestion();

                return [
                    'questionList' => $allQuestion,
                    'logicList' => $xml_utils->getLogic()
                ];
            });

            $questionList[ $info->nxs_enquete_no ] = $eqtXmlData['questionList'];
            $logicList[ $info->nxs_enquete_no ] = $option['logic_name']??false ? $eqtXmlData['logicList'][ $option['logic_name'] ] : $eqtXmlData['logicList'];
        }

        return [
            'questionList' => $questionList,
            'logicList' => $logicList
        ];
    }

    /**
     * 案件xmlから設問リストを作成
     *
     * @param int $ank_id
     * @param array $parts_no
     * @return array
     */
    public function getQuestionList($ank_id, array $parts_no = []){
        $xmlData = $this->getXmlData($ank_id, $parts_no);

        $questionList = [];
        foreach($xmlData['questionList'] as $info){
            $questionList = array_merge($questionList, $info);
        }
        return $questionList;
    }
}
