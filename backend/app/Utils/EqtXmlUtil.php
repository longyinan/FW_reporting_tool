<?php

namespace App\Utils;

use SimpleXMLElement;

class EqtXmlUtil
{
	// 読み込むXMLデータ。SimpleXMLオブジェクト形式。
	private $xml_strc  = NULL;	// 構成情報
	private $xml_logic = NULL;	// ロジック情報
    private $questionList = null;
    private $logicList = [
        'required_condition' => [],
        'ma_exclusion' => [],
        'end_condition' => [],
        'order_change' => [],
        'user_definition' => [],
        'trans_condition' => [],
        'data_setting' => [],
        'page_timer' => [],
    ];

    public function __construct($structure, $logic)
    {
        $this->xml_strc = simplexml_load_string($structure, 'SimpleXMLElement', LIBXML_NOCDATA);
        $this->xml_logic = simplexml_load_string($logic, 'SimpleXMLElement', LIBXML_NOCDATA);


    }

    public function getAllQuestion(): array{
        if($this->questionList) return $this->questionList;

        $questionList = [];
        $pageNum = 0;
		foreach( $this->xml_strc->structure[0] as $item ) {
			switch( $item->getName() ) {
                case 'pagebreak':
                    $pageNum++;
                    break;
				case 'question':
                    $question = $this->analysisQuestionXmltoArr($item);
                    $question['page'] = $pageNum;
                    $questionList[] = $question;
                    break;
				case 'qgroup':
                    $question = [
                        'type' => $item->property->type->__toString(),
                        'qNo' => $item->property->qNo->__toString(),
                        'name' => $this->_filter_questionqno($item->property->name),
                        'page' => $pageNum,
                        'subQuestions' => [],
                    ];
                    foreach($item->question as $sub_question){
                        $subQuestion = $this->analysisQuestionXmltoArr($sub_question);
                        $subQuestion['page'] = $pageNum;
                        $question['subQuestions'][] = $subQuestion;
                    }
                    $questionList[] = $question;
                    break;
			}
		}

        return $this->questionList = $questionList;
    }

    protected function analysisQuestionXmltoArr(SimpleXMLElement $question): array{
        //TODO 補足？
        return
        [
            'qCol' => $question->property->qCol->__toString(),
            'qNo' => strtoupper($question->property->qCol->__toString()),//表示番号ではなく設問番号の大文字
            'name' => $this->_filter_questionqno($question->property->name->__toString()),
            'type' => $question->property->type->__toString(),
            'categories' => $this->getAllCategories( $question->categories->category )
        ];
    }

    public function getAllCategories(SimpleXMLElement $categories): array{
        $categoryList = [];
        $catNo = 1;
        foreach($categories as $item){
            if($item->groupid == ''){
                $id = $item->attributes()->id->__toString();
                $categoryList[ $id ] = $this->analysiCategoryXmltoArr($item);
                $categoryList[ $id ][ 'catNo' ] = $catNo++;
            }
            //その他FA（ループ中必ず所属カテの後ろと想定）
            else{
                $otherFa = $this->analysiOtherFaXmltoArr($item);
                $categoryList[ $otherFa['groupid'] ]['otherFa'][] = $otherFa;
            }
        }

        return array_values($categoryList);
    }

    protected function analysiCategoryXmltoArr(SimpleXMLElement $category): array{
        //TODO 補足？
        //MEMO　p2ではcatNoとvalueが意味ない、そちらと一致
        return
        [
            // 'catNo' => $category->catNo->__toString(),
            // 'value' => $category->value->__toString(),
            'name' => $this->_filter_questionqno($category->name->__toString()),
            'otherlimit' => $category->otherlimit->__toString(),
            'othertype' => $category->othertype->__toString(),
            'othernummax' => $category->othernummax->__toString(),
            // 'content' => $this->changeCContent( trim( $category->content )),
            'otherFa' => []
        ];
    }

    protected function analysiOtherFaXmltoArr(SimpleXMLElement $otherFa): array{
        //TODO 補足？
        return
        [
            'groupid' =>   $otherFa->groupid->__toString(),
            'othertype' => $otherFa->othertype->__toString(),
            'otherlimit' => $otherFa->otherlimit->__toString(),
            'othernummax' => $otherFa->othernummax->__toString(),
            // 'othersort' => $otherFa->othersort->__toString()
        ];
    }


    public function getLogic(?string $name = null): array{
		// ループ用に初期化
		$lp_cnt_lg = 0;
		$lp_max_lg = count( $this->xml_logic->logic );

		while( $lp_cnt_lg < $lp_max_lg ) {
			$logic = $this->xml_logic->logic[ $lp_cnt_lg ];
			++$lp_cnt_lg;

			$elementId = $logic->attributes()->elementID->__toString();
            $id = $logic->attributes()->id->__toString();
            $logic_type = $logic->attributes()->type->__toString();
			switch( $logic_type ) {
                case 'required_condition';
                    if ( trim( $logic->expression ) == '' ) break;

                    $this->logicList[ $logic_type ][$elementId] = array(
                        'message' => $logic->message != '' ? trim( $logic->message->__toString() ) : '回答の仕方に誤りがあります。',
                        'expression' => $logic->expression->__toString()
                    );
                    break;
                case 'ma_exclusion';
                    break;
                case 'end_condition';
                    $this->logicList[ $logic_type ][] = array(
                        'expression' => trim( $logic->expression->__toString() ),
                        'endtime' => $logic->endtime == '' ? '0' : $logic->endtime->__toString()
                    );
                    break;
                case 'order_change';
                    break;
                case 'user_definition';
                    $user_groupid = $logic->user_groupid->__toString();
                    $param_name = $logic->param_name != '' ? $logic->param_name->__toString() : $this->logicList[ $logic_type ][ $user_groupid ]['param_name'];
                    $this->logicList[ $logic_type ][ $id ] = array(
                        'param_name' => $param_name,//アイテム名
                        'store' => trim( $logic->store->__toString() ),//格納する内容
                        'store_cond' => trim( $logic->store_cond->__toString() ),//格納する条件
                        'delivery' => $logic->delivery->__toString(),//納品用カラム
                        'user_groupid' => $user_groupid
                    );
                    break;
                case 'trans_condition';
                    $this->logicList[ $logic_type ][] = array(
                        'expression' => trim( $logic->expression->__toString() ),
                        'goTo_enqNo' => $logic->goTo_enqNo->__toString()
                    );
                    break;
                case 'data_setting';
                    break;
                case 'page_timer';
                    break;
            }
        }

        return $name ? ($this->logicList[ $name ]??[]): $this->logicList;
    }


    /** ==================================================================================================================
	 * データ整形QNo変換メソッド
	 * [q1]、[&q1]を○○○に変換する
	 *
	 * @access	private
	 * @param 	String $val  設問文等のテキスト内容
	 * @return String 戻り値
	 * ================================================================================================================== */
	protected function _filter_questionqno( $val ) {
		$val = preg_replace( '/\[(&|&amp;)([a-z]+[a-z0-9_]*)\]/', '○○○（$2回答テキスト再掲）', $val, -1 );
		$val = preg_replace( '/\[([a-z]+[a-z0-9_]*)\]/', '○○○（$1回答再掲）', $val, -1 );
		return preg_replace('/^\s+|\s+$/u', '', html_entity_decode($val));
	}
}


