<?php
namespace App\Utils;

// クラス定義
class CodeConvertorUtils {
	// セパレータ
	const SEP = "#";

	// 正規表現
	const REG_QUESTION      = '\[[a-z]+[a-z0-9_]*[a-z0-9]?\]';
	const REG_QUESTION_TEXT = '\[\[a-z]+[a-z0-9_]*[a-z0-9]?\]';
	const REG_STRING        = '[\\\]{0}"(\\\"|\\\{0}[^"])*[\\\]{0}"';
	const REG_STRING_2        = "[\\\]{0}'(\\\'|\\\{0}[^'])*[\\\]{0}'";
	const REG_CONDITION     = "([^!<>=]+)(=|!=|>=|<=|<|>)([^!<>=]+)";

	// 設問リスト。
	// 例：$qList[ "q1" ] = array( "type" => "SA", "category" => 100, "no" => 0, "pageNo" => 1 );
	private $qList = array();

	private $attrColMap = [];

	// 定義関数一覧
	private $funcList = array(
		array( 'sum\\(((\[[a-z+[a-z0-9_]*[a-z0-9]?\]|,|\[[a-z]+[a-z0-9_]*\{{1}([0-9\&:,])+\}{1}[a-z0-9_]*\]|[a-zA-Z0-9:,\@])+)\\)', "EqtfSum" ),
		array( 'count\\(\[([a-z+[a-z0-9_]*[a-z0-9]?)\]{1}\\)', "EqtfCount" )
	);

    public $errMessage;

	public function __construct($questionList)
	{
			foreach(config('common.CONDITION_ATTRIBUTE_COL') as $realCol => $col){
				$this->attrColMap[$col] = is_numeric($realCol) ? 1 : $realCol;
			}

			foreach($questionList as  $question){
					switch($question['type']){
							case 'SA':
							case 'MA':
									$this->qList[ $question['qCol'] ] =  [ "type" => $question['type'], "category" => count($question['categories'])];

									foreach($question['categories'] as $category){
											foreach($category['otherFa'] as $sort => $otherFa){
													$colname = $question['qCol'] . '_snt' . $category['catNo'] . '_' . ($sort + 1);
													$this->qList[ $colname ] = [ "type" => 'FA', "category" => 1 ];
											}
									}
									break;
							case 'FA':
							case 'NU':
                                    if(empty($question['addFlg'])){
                                        foreach($question['categories'] as $category){
                                            $colname = $question['qCol'] . '_' . $category['catNo'];
                                            $this->qList[ $colname ] =  [ "type" => $question['type'], "category" => 1 ];
                                        }
                                    }

									break;
							default:
									$this->qList[ $question['qCol'] ] =  [ "type" => $question['type'], "category" => 1 ];
					}
			}
	}

	/**
	 * 式変換処理本体。
	 * $str  : 変換元の文字列
	 * $attrTableName  : attrカラム使うテーブル名
	 * return string|boolean
	 */
	public function convert(string $str, $attrTableName = null ) {
		try {
			$this->errMessage = [];

			// 改行除去
			$str = preg_replace( '/\r/', "", $str );
			$str = preg_replace( '/\n/', "", $str );

			// 文字列除去
			$cnt = 1;
			$stringData = array();
			while( preg_match( '/'. self::REG_STRING .'/', $str, $tmp ) == 1 ) {
				$repText = "@string" . $cnt . "@";
				//例 "a\"b'c" => 'a\"b''c'
				$stringData[ $cnt ] = "'" . str_replace("'", "''", substr($tmp[ 0 ], 1, strlen($tmp[ 0 ]) - 2)) . "'" ;
				$str = preg_replace( '/'. self::REG_STRING .'/', $repText, $str, 1 );
				++$cnt;
			}

			while( preg_match( '/'. self::REG_STRING_2 .'/', $str, $tmp ) == 1 ) {
				$repText = "@string" . $cnt . "@";
				//例 'a"b\'c' => 'a"b''c'
				$stringData[ $cnt ] = str_replace("\'", "''", $tmp[ 0 ]);
				$str = preg_replace( '/'. self::REG_STRING_2 .'/', $repText, $str, 1 );
				++$cnt;
			}

			// スペース除去・小文字化
			$str = preg_replace( "/ /", "", $str );
			$str = strtolower( $str );

			// 関数除去
			$cnt = 1;
			$functionData = array();
			do {
				$isAllReplace = true;
				reset( $this->funcList );
				foreach( $this->funcList as $funcData ) {
					if ( preg_match( '/' . $funcData[0] . '/', $str, $tmp ) == 1 ) {
						$repText = '@function' . $cnt . '@';
                        $func = $funcData[1];
						$functionData[ $cnt ] = $this->$func($tmp);
						$str = preg_replace( '/' . $funcData[0] . '/', $repText, $str, 1 );
						$isAllReplace = false;
						++$cnt;
					}
				}
			} while( !$isAllReplace );

			//SQLインジェクション対応
			$str = str_replace(['$', ';'], '', $str);

			// 無駄なカッコを除く ([sc1] + [sc2]) < (1)    =>    [sc1] + [sc2] < 1
			// カッコ内は必ず　=　!=　<　<=　>　>=　が含むと想定
			$regpex = "/\(([0-9]+(?:&:|&|:|,)?[0-9]*)\)/";
			while( preg_match( $regpex, $str, $tmp ) == 1 ) {
				$str = preg_replace( $regpex, "\\1", $str, 1 );
			}

            $regpex = "/\(([^(?:=|!=|<|<=|>|>=)]+)\)/";
			while( preg_match( $regpex, $str, $tmp ) == 1 ) {
				$str = preg_replace( $regpex, "\\1", $str, 1 );
			}

			// カッコを検出して分解する
			$part = $this->sepPriority( $str );
			if ( $part === false ) {
				return false;
			}

			// and or で分解する
			$part = $this->sepAndOr( $part );
			if ( $part === false ) {
				return false;
			}

			// 式を分解する
			$part = $this->sepCondition( $part );
			if ( $part === false ) {
				return false;
			}

			// 式を変換する
			$maxLoop = count( $part );
			for ( $i = 0; $i < $maxLoop; ++$i ) {

				// 展開可能な条件式でなければ、次の条件へ
				if ( preg_match( '/'.self::REG_CONDITION.'/', $part[ $i ], $tmp ) != 1 ) {
					continue;
				}

				// 左辺から設問番号を取得
				$qno_left = NULL;
				if ( preg_match( "/^\[([a-z]+[a-z0-9_]*[a-z0-9]?)\]$/", $tmp[1], $tmp2 ) == 1 ) {
					$qno_left = $tmp2[1];
				}

				// 右辺から設問番号を取得
				$qno_right = NULL;
				if ( preg_match( "/^\[([a-z]+[a-z0-9_]*[a-z0-9]?)\]$/", $tmp[3], $tmp3 ) == 1 ) {
					$qno_right = $tmp3[ 1 ];
				}

				// ↓#6807対応 内容が0で始まる数字の場合、ダブルクオートを追加
				if ($tmp[3] <> '0' && substr($tmp[3], 0, 1) == '0' && preg_match( '/\D/', $tmp[3] ) == false ) {
					$tmp[3] = '\'' . $tmp[3] . '\'';
				}
				// ↑ #6807対応

				// 右辺の内容が数値～数値の省略形であった場合、条件式を作り替える
				if( preg_match( "/^([0-9]+)(&?:)([0-9]+)$/", $tmp[3], $tmp2 ) ) {
					if ( $tmp2[1] == $tmp2[3] ) {
						$tmp[3] = $tmp2[1];// 2:2ような場合
					} else {
						if ( $qno_left != NULL && (bool)$this->qList && isset( $this->qList[ $qno_left ][ 'type' ] ) && $this->qList[ $qno_left ][ 'type' ] == 'MA' ) {
								// MAだったら何もしない（MAは関数にて処理を入れるので、事後処理対応とする）
						} else {
								array_splice( $part, $i + 1, 0, ')' );
								++$maxLoop;

								switch( $tmp[ 2 ] ) {
										case '=':
												array_splice( $part, $i + 1, 0, $tmp[1] . '<=' . $tmp2[3] );
												++$maxLoop;
												$op = 'and';
												array_splice( $part, $i + 1, 0, $op );
												++$maxLoop;
												// $tmp[ 1 ] .= '::numeric';
												$tmp[ 2 ] = '>=';
												$tmp[ 3 ] = $tmp2[ 1 ];
												break;
										case '!=':
												array_splice( $part, $i + 1, 0, $tmp[1] . '>' . $tmp2[3] );
												++$maxLoop;
												$op = 'or';
												array_splice( $part, $i + 1, 0, $op );
												++$maxLoop;
												// $tmp[ 1 ] .= '::numeric';
												$tmp[ 2 ] = '<';
												$tmp[ 3 ] = $tmp2[ 1 ];
												break;
										default:
												return false;
												break;
								}

								array_splice( $part, $i, 0, '(' );
								++$maxLoop;
								++$i;

						}
					}
				}

				$repText = "";

				// 左辺の設問番号を変換する
				while( preg_match( "/\[([a-z]+[a-z0-9_]*[a-z0-9]?)\]/", $tmp[1], $tmp2 ) == 1 ) {
					if(!isset($this->qList[ $tmp2[1]  ])){
							$this->errMessage[] = trans("ratio.conditionErrorCol",[$tmp2[1] ]);
					}
					// 置換文字列を生成
					if( in_array($this->qList[ $tmp2[1] ]['type'], ['FA', 'MA']) ){
							$repText = $tmp2[1];
					}
					elseif( $this->qList[ $tmp2[1] ]['type'] == '' ){
							$repText = $tmp2[1] . (preg_match('/^@string\d+@$/', $tmp[ 3 ]) || $tmp[ 3 ] == 'null' ? '' : '::numeric');
					}
					else{
							$repText = $tmp2[1] . '::numeric';
					}

					//複数attrTable検索対応
					if(isset($this->attrColMap[ $tmp2[1]])){
						$realCol = $this->attrColMap[ $tmp2[1]] === 1 ? $tmp2[1] : $this->attrColMap[ $tmp2[1]];
						if($attrTableName !== null) $realCol = "{$attrTableName}.{$realCol}";//複数attrTable検索対応
						$repText = str_replace($tmp2[1], $realCol, $repText);
					}

					$tmp[1] = preg_replace( "/\[([a-z]+[a-z0-9_]*[a-z0-9]?)\]/", $repText, $tmp[1], 1 );
				}

				// 右辺の設問番号を変換する
				while( preg_match( "/\[([a-z]+[a-z0-9_]*[a-z0-9]?)\]/", $tmp[3], $tmp2 ) == 1 ) {
					if(!isset($this->qList[ $tmp2[3]  ])){
							$this->errMessage[] = trans("ratio.conditionErrorCol",[$tmp2[1] ]);
					}
					// 置換文字列を生成
					if( in_array($this->qList[ $tmp2[3] ]['type'], ['FA', 'MA']) ){
							$repText = $tmp2[3];
					}
					elseif( $this->qList[ $tmp2[3] ]['type'] == '' ){
							$repText = $tmp2[3] . (preg_match('/^@string\d+@$/', $tmp[ 1 ]) || $tmp[ 1 ] == 'null' ? '' : '::numeric');
					}
					else{
							$repText = $tmp2[3] . '::numeric';
					}

					if(isset($this->attrColMap[ $tmp2[1]])){
						$realCol = $this->attrColMap[ $tmp2[1]] === 1 ? $tmp2[1] : $this->attrColMap[ $tmp2[1]];
						if($attrTableName !== null) $realCol = "{$attrTableName}.{$realCol}";//複数attrTable検索対応
						$repText = str_replace($tmp2[1], $realCol, $repText);
					}

					$tmp[3] = preg_replace( "/\[([a-z]+[a-z0-9_]*[a-z0-9]?)\]/", $repText, $tmp[3], 1 );
				}

				// 演算子に応じて、条件式を構築する
				switch( $tmp[ 2 ] ) {

					case "=":
						// MAの場合
						if ( $qno_left != NULL && (bool)$this->qList && isset( $this->qList[ $qno_left ][ 'type' ] ) && $this->qList[ $qno_left ][ 'type' ] == 'MA' ) {
								if( preg_match( "/^([0-9]+)(&?:)([0-9]+)$/", $tmp[3], $tmp2 ) ) {
										$tmpStr = str_repeat('_', $this->qList[ $qno_left ][ 'category' ]);
										$min = min($tmp2[1], $tmp2[3]);
										$max = max($tmp2[1], $tmp2[3]);
										if($tmp2[ 2 ] == '&:'){
												for($curr = $min; $curr <= $max; $curr++){
														$tmpStr = substr_replace($tmpStr, '1', $curr - 1, 1);
												}
												$repText = $tmp[ 1 ] . " like '" . $tmpStr . "'";
										}
										else{
												$repTextArr = [];
												for($curr = $min; $curr <= $max; $curr++){
														$repTextArr[] = $tmp[ 1 ] . " like '" . substr_replace($tmpStr, '1', $curr - 1, 1) . "'";
												}

												$repText = '(' . implode(" or ", $repTextArr) . ')';
										}
								} else if ( $tmp[3] == 'null' ) {
										$repText = $tmp[ 1 ] . ' is ' . $tmp[ 3 ];
								} else {
										$tmpStr = str_repeat('_', $this->qList[ $qno_left ][ 'category' ]);
										$repText = $tmp[ 1 ] . " like '" . substr_replace($tmpStr, '1', $tmp[ 3 ] - 1, 1) . "'";
								}
						} else {
								// 演算子を決める
								$ope = $tmp[ 1 ] == 'null' || $tmp[ 3 ] == 'null' ? 'is' : '=';
								// 条件文を生成
								$repText = $tmp[ 1 ] . ' ' . $ope . ' ' . $tmp[ 3 ];
						}

						// NULL処理を追加
						if ( $qno_left != null && $tmp[ 3 ] != 'null' && isset( $this->qList[ $qno_left ][ 'type' ] ) && ( $this->qList[ $qno_left ][ 'type' ] == 'FA' || $this->qList[ $qno_left ][ 'type' ] == 'NU' ) ) {
								$repText = $qno_left != NULL ? '( ' . $tmp[1] . ' is not null and '. $repText . ' )' : $repText;
						}
						break;

					case "!=":
						// MAの場合
						if ( $qno_left != NULL && (bool)$this->qList && isset( $this->qList[ $qno_left ][ 'type' ] ) && $this->qList[ $qno_left ][ 'type' ] == 'MA' ) {
								if( preg_match( "/^([0-9]+)(&?:)([0-9]+)$/", $tmp[3], $tmp2 ) ) {
										$tmpStr = str_repeat('_', $this->qList[ $qno_left ][ 'category' ]);
										$min = min($tmp2[1], $tmp2[3]);
										$max = max($tmp2[1], $tmp2[3]);
										if($ope = $tmp2[ 2 ] == '&:'){
												for($curr = $min; $curr <= $max; $curr++){
														$tmpStr = substr_replace($tmpStr, '1', $curr - 1, 1);
												}
												$repText = $tmp[ 1 ] . " not like '" . $tmpStr . "'";
										}
										else{
												$repTextArr = [];
												for($curr = $min; $curr <= $max; $curr++){
														$repTextArr[] = $tmp[ 1 ] . " not like '" . substr_replace($tmpStr, '1', $curr - 1, 1) . "'";
												}

												$repText = '(' . implode(" and ", $repTextArr) . ')';
										}
								} else if ( $tmp[3] == 'null' ) {
										$repText = $tmp[ 1 ] . ' is not ' . $tmp[ 3 ];
								} else {
										$tmpStr = str_repeat('_', $this->qList[ $qno_left ][ 'category' ]);
										$repText = $tmp[ 1 ] . " not like '" . substr_replace($tmpStr, '1', $tmp[ 3 ] - 1, 1) . "'";
								}
						} else {
								// 演算子を決める
								$ope = $tmp[ 1 ] == 'null' || $tmp[ 3 ] == 'null' ? 'is not' : '!=';
								// 条件文を生成
								$repText = $tmp[ 1 ] . ' ' . $ope . ' ' . $tmp[ 3 ];
						}
						// NULL処理を追加
						if ( $qno_left != null && $tmp[ 3 ] != 'null' ) {
								$repText = $qno_left != NULL ? '( ' . $tmp[1] . ' is not null and '. $repText . ' )' : $repText;
						}
						break;

					case ">=":
					case ">":
					case "<=":
					case "<":
						// 不正チェック
						if ( $tmp[ 1 ] == "null" || $tmp[ 3 ] == "null" ) {
							return false;
						}
						if ( $qno_left != NULL && (bool)$this->qList && isset( $this->qList[ $qno_left ][ 'type' ] ) && $this->qList[ $qno_left ][ 'type' ] == 'MA' ) {
							return false;
						}
						// 条件文を生成
						$repText = $tmp[ 1 ] . ' ' . $tmp[ 2 ] . ' ' . $tmp[ 3 ];
						// NULL処理を追加
						if ( $qno_right !== NULL ) {
								$repText = $tmp[3] . ' is not null and ' . $repText;
						}

						if ( $qno_left !== NULL ) {
								$repText = $tmp[1] . ' is not null and ' . $repText;
						}

						if ( $qno_left !== NULL || $qno_right !== NULL ) {
								$repText = '( ' . $repText . ' )';
						}
						break;

				}

				$part[ $i ] = $repText;

			}

			// 式を結合する
			$result = implode( ' ', $part );
			if ( $part === false ) {
				return false;
			}

			// 関数を戻す
			$maxCnt = count( $functionData );
			for ( $i = $maxCnt; $i > 0; --$i ) {
				$result = preg_replace( '/@function' . $i . '@/', $functionData[ $i ], $result );
			}


			// 残った演算子を置換処理
			$result = preg_replace( '/[ ]{0}(\(|\)|\,|\+|\-|\/|\*|\%)[ ]{0}/', " \\1 " , $result );
			$result = preg_replace( '/([^0-9]{1})(\.)([^0-9]{1})/', "\\1 \\2 \\3" , $result );

			// 不要なスペースを消す
			$result = preg_replace( '/[ ]{2,}/', ' ', $result );
			$result = trim( $result );

			// No.699 : 2013.11.8 H.Yamamoto ADD --->>
			// 不要な囲み文字「( )」を外す
			if ( preg_match( '/^\((.+)\)$/', $result, $tmp ) === 1
				 && ( ( preg_match_all( '/\(/', $result, $sute ) === 1 && preg_match_all( '/\)/', $result, $sute ) === 1 ) || ( preg_match( '/\).+\(/', $result, $sute ) === 0 ) )
			   ) {
				$result = trim( $tmp[1] );
			}
			// No.699 : 2013.11.8 H.Yamamoto ADD <<---

			// 文字列を戻す
			$maxCnt = count( $stringData );
			for ( $i = 1; $i <= $maxCnt; ++$i ) {
				$searchStr = '@string' . $i . '@';
				$replaceStr = $stringData[ $i ];

				//あいまい検索条件
				$likeFlg = false;
				if(preg_match("/^'\*/", $replaceStr)){
					$replaceStr = "'%" .substr($replaceStr, 2);
					$likeFlg = true;
				}

				if(preg_match("/\*'$/", $replaceStr)){
					$replaceStr = substr($replaceStr, 0, -2) . "%'" ;
					$likeFlg = true;
				}

				if($likeFlg){
					$searchStr = "=\s{$searchStr}";
					$replaceStr = 'LIKE ' . $replaceStr;
				}

				//\\を\\\\に変換
				$result = preg_replace( '/'.$searchStr.'/', str_replace('\\\\', '\\\\\\\\', $replaceStr), $result );
			}

			if(!empty($this->errMessage)) return false;

			return $result;

		} catch ( \Exception $e ) {
			return false;
		}

		return $result;

	}

	/**
	 * カッコを検出して分解する
	 *
	 */
	private function sepPriority(string $str ) {
		if ( substr_count( $str, "(" ) !== substr_count( $str, ")" ) ) {
			return false;
		}
		$str = preg_replace( "/(\(|\))/", self::SEP . ("\\1") . self::SEP, $str );
		return preg_split( "/" . self::SEP . "/", $str, 0, PREG_SPLIT_NO_EMPTY );
	}

	/**
	 * and or で分解する
	 *
	 */
	private function sepAndOr(array $parts ): array {
		$result = array();
		foreach( $parts as $part ) {

			// カラム名部を取り除く
			preg_match_all( '/\[([a-z]+[a-z0-9\&:,_\{\}]*)\]/', $part, $matchColumNames );
			if ( (bool)$matchColumNames ) {
				$matchColumNames = array_unique( $matchColumNames[ 1 ] );
				foreach( $matchColumNames as $k => $v ) {
					$part = preg_replace( '/\['.$v.'\]/', '[COLUMN_'.$k.']', $part );
				}
			}

			// and / or 部検出
			$str = preg_replace( "/(and|or)/", self::SEP . "\\1" . self::SEP, $part );

			// カラム名部復元
			if ( (bool)$matchColumNames ) {
				foreach( $matchColumNames as $k => $v ) {
					$str = preg_replace( '/\[COLUMN_'.$k.'\]/', '['.$v.']', $str );
				}
			}

			// 分解
			$result = array_merge( $result, preg_split( "/" . self::SEP . "/", $str, 0, PREG_SPLIT_NO_EMPTY ) );

		}
		return $result;
	}

	/**
	 * 式を分解する
	 *
	 */
	private function sepCondition(array $parts ): array {
		$result = array();
		foreach( $parts as $part ) {
			$str = preg_replace( "/(!=|>=|<=|=|>|<)/", self::SEP . ("\\1") . self::SEP, $part );
			$parts2 = preg_split( "/" . self::SEP . "/", $str, 0, PREG_SPLIT_NO_EMPTY );
			if ( count( $parts2 ) == 3 ) {
				list( $left, $leftOpe ) = $this->sepSide( $parts2[ 0 ] );
				if ( $left === false && $leftOpe === false ) {
					return false;
				}
				list( $right, $rightOpe ) = $this->sepSide( $parts2[ 2 ] );
				if ( $right === false && $rightOpe === false ) {
					return false;
				}

				$loop1 = 0;

				if ( count( $left ) > 1 ) {
					$result = array_merge( $result, array( "(" ) );
				}
				foreach( $left as $leftVal ) {
					if ( $loop1 > 0 ) {
						$result = array_merge( $result, array( $leftOpe[ $loop1 - 1 ] ) );
					}
					if ( count( $right ) > 1 ) {
						$result = array_merge( $result, array( "(" ) );
					}
					$loop2 = 0;
					foreach( $right as $rightVal ) {
						if ( $loop2 > 0 ) {
							$result = array_merge( $result, array( $rightOpe[ $loop2 - 1 ] ) );
						}
						$result = array_merge( $result, array( $leftVal . $parts2[ 1 ] . $rightVal ) );
						++$loop2;
					}
					if ( count( $right ) > 1 ) {
						$result = array_merge( $result, array( ")" ) );
					}
					++$loop1;
				}
				if ( count( $left ) > 1 ) {
					$result = array_merge( $result, array( ")" ) );
				}
			} else {
				switch( $parts2[ 0 ] ) {
					case "and":
						$result = array_merge( $result, array( "and" ) );
						break;
					case "or":
						$result = array_merge( $result, array( "or" ) );
						break;
					default:
						$result = array_merge( $result, $parts2 );
						break;
				}
			}
		}

		return $result;
	}

	/**
	 * 左右の式を分解する
	 *
	 */
	private function sepSide(string $str ): array{
		$result = array();
		$str = preg_replace( "/(\+|\-|\/|\*|\%|\.)/", self::SEP . ("\\1") . self::SEP, $str );
		$tmp = preg_split( "/" . self::SEP . "/", $str, 0, PREG_SPLIT_NO_EMPTY );

		if ( count( $tmp ) > 1 ) {
			// 複数ある　：　省略形は無視
			$result = array( array( implode( "", $tmp ) ), array() );
		} else {
			// 単数しかない　：　省略形の可能性あり
			if ( substr( $tmp[0], 0, 1 ) == "[" ) {
				if ( strpos( $tmp[0], "{" ) !== false ) {
					$before = substr( $tmp[0], 0, strpos( $tmp[0], "{" ) );
					$after  = substr( $tmp[0], strpos( $tmp[0], "}" ) + 1 );
					$str    = substr( $tmp[0], strpos( $tmp[0], "{" ) + 1, strpos( $tmp[0], "}" ) - strpos( $tmp[0], "{" ) - 1 );

					$str = preg_replace( "/(&:|&|:|,)/", self::SEP . ("\\1") . self::SEP, $str );
					$tmp = preg_split( "/" . self::SEP . "/", $str, 0, PREG_SPLIT_NO_EMPTY );
					$cntLoop = count( $tmp );
					if ( $cntLoop >= 2 ) {
						for( $i = 0; $i < $cntLoop; $i += 2 ) {
							if ( !is_numeric( $tmp[ $i ] ) ) {
								return array( false, false );
							}
						}
					}

					$tmp[] = NULL;

					$val = array();
					$ope = array();

					if ( count( $tmp ) > 2 ) {
						for( $i = 1, $cntLoop = count( $tmp ); $i < $cntLoop; $i+=2 ) {
							switch( $tmp[ $i ] ) {
								case "&":
									$val[] = $before . $tmp[ $i - 1 ] . $after;
									$ope[] = "and";
									break;
								case ",":
									$val[] = $before . $tmp[ $i - 1 ] . $after;
									$ope[] = "or";
									break;
								case "&:":
									/* modify yamamoto 2011/3/25 No.1115 */
									$diff = $tmp[ $i + 1 ] < $tmp[ $i - 1 ] ? 1 : -1;
									//$addValue = range( $tmp[ $i - 1 ], $tmp[ $i + 1 ] - 1 );
									$addValue = range( $tmp[ $i - 1 ], $tmp[ $i + 1 ] + $diff );
									/* modify yamamoto 2011/3/25 */
									foreach( $addValue as $addVal ) {
										$val[] = $before . $addVal . $after;
										$ope[] = "and";
									}
									break;
								case ":":
									/* modify yamamoto 2011/3/25 No.1115 */
									$diff = $tmp[ $i + 1 ] < $tmp[ $i - 1 ] ? 1 : -1;
									//$addValue = range( $tmp[ $i - 1 ], $tmp[ $i + 1 ] - 1 );
									$addValue = range( $tmp[ $i - 1 ], $tmp[ $i + 1 ] + $diff );
									/* modify yamamoto 2011/3/25 */
									foreach( $addValue as $addVal ) {
										$val[] = $before . $addVal . $after;
										$ope[] = "or";
									}
									break;
								default:
									$val[] = $before . $tmp[ $i - 1 ] . $after;
									break;
							}
						}
					} else {
						$val = array( $before . $tmp[ 0 ] . $after );
						$ope = array();
					}
					$result = array( $val, $ope );
				} else {
					// 設問番号
					$result = array( array( implode( "", $tmp ) ), array() );
				}

			} else {
				// その他の値
				//$str = preg_replace( "/(&:|&|:|,)/", self::SEP . ("\\1") . self::SEP, $tmp[0] );

				// &: が & と間違って分割されてしまう為、違うものに置き換えておく
				$reptmp = preg_replace( "/&:/", '@----@', $tmp[0] );

				$str = preg_replace( "/(&|,)/", self::SEP . ("\\1") . self::SEP, $reptmp );
				$tmp = preg_split( "/" . self::SEP . "/", $str, 0, PREG_SPLIT_NO_EMPTY );
				$cntLoop = count( $tmp );

				// 置き換えたものを元に戻す
				for ( $i = 0; $i < $cntLoop; ++$i ) {
					$tmp[$i] = preg_replace( '/@----@/', '&:', $tmp[$i] );
				}

				$tmp[] = NULL;

				$val = array();
				$ope = array();

				if ( count( $tmp ) > 2 ) {
					for( $i = 1, $cntLoop = count( $tmp ); $i < $cntLoop; $i+=2 ) {
						switch( $tmp[ $i ] ) {
							case "&":
								$val[] = $tmp[ $i - 1 ];
								$ope[] = "and";
								break;
							case ",":
								$val[] = $tmp[ $i - 1 ];
								$ope[] = "or";
								break;
							case "&:":
								/* modify yamamoto 2011/3/25 No.1115 */
								$diff = $tmp[ $i + 1 ] < $tmp[ $i - 1 ] ? 1 : -1;
								//$addValue = range( $tmp[ $i - 1 ], $tmp[ $i + 1 ] - 1 );
								$addValue = range( $tmp[ $i - 1 ], $tmp[ $i + 1 ] + $diff );
								/* modify yamamoto 2011/3/25 */
								foreach( $addValue as $addVal ) {
									$val[] = $addVal;
									$ope[] = "and";
								}
								break;
							case ":":
								/* modify yamamoto 2011/3/25 No.1115 */
								$diff = $tmp[ $i + 1 ] < $tmp[ $i - 1 ] ? 1 : -1;
								//$addValue = range( $tmp[ $i - 1 ], $tmp[ $i + 1 ] - 1 );
								$addValue = range( $tmp[ $i - 1 ], $tmp[ $i + 1 ] + $diff );
								/* modify yamamoto 2011/3/25 */
								foreach( $addValue as $addVal ) {
									$val[] = $addVal;
									$ope[] = "or";
								}
								break;
							default:
								$val[] = $tmp[ $i - 1 ];
								break;
						}
					}
				} else {
					$val = array( $tmp[ 0 ] );
					$ope = array();
				}
				$result = array( $val, $ope );
			}
		}

		return $result;
	}

	private function EqtfCount(array $matchArr): string{
			if(!isset($this->qList[ $matchArr[1] ]) || $this->qList[ $matchArr[1] ][ 'type' ] !== 'MA'){
					$this->errMessage[] = trans("ratio.conditionErrorCount",[$matchArr[1]]);
			}

			return sprintf("LENGTH(REPLACE(%s,'0',''))", $matchArr[1]);
	}

	private function EqtfSum(array $matchArr): string{
			$result = $this->sepSide( $matchArr[1]);

			$qColList = array();
			foreach($result[0] as $str){
					//設問番号の解析
					if( preg_match_all( "/\[([a-z]+[a-z0-9_]*[a-z0-9]?)\]/", $str, $tmp ) )
					{
							foreach($tmp[1] as $qCol){
									//カラムの存在を確認
									if( !isset($this->qList[ $qCol ]) || $this->qList[ $qCol ][ 'type' ] !== 'NU'){
											$this->errMessage[] = trans("ratio.conditionErrorSum",[$qCol]);
									}

									$qColList[] = $qCol . '::numeric';
							}
					}
			}

			return sprintf('round((%s)::numeric,3)', implode(' + ', $qColList));
	}
}
