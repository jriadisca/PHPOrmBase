<?php 
/* ********************************************************************************
	簡単に文字検索ができるようにする基底クラス
   ********************************************************************************
   
	【解説】
		テーブル構造は、「単一のテーブル」のみとしている
			メタデータテーブル、タグデータテーブルは別途別の基底クラスを用意する
			
		必須として派性クラスのメンバに、文字検索をするカラム名 var $search_columns 定義する必要がある
		設計思想として、ページャーを意識して作成している。
		
		
		＜定義の例＞
			include_once('search.class.php');
			class myclass extends search{
				var $search_columns = array('name', 'email');
				// この場合メールアドレスと名前がキーワード検索対象になる
			}
			
		＜使用時の例＞
			$objMy= new myclass($mdb2);
			$res= $objMy->getsByArgs(array('word' => $_GET['word'], 'offset' => 10, 'limit' => 100, ));
			$list= array();
			while (($row = $res->fetchRow())) {
				$list[] = $row;
			}
			$sth->free();
			
							
	【検索パラメータ】
		input $args : 
			例： array('word'=> 'hogehoge ') キーワード検索（スペースキーで複数の文字列をOR検索可能）
			例： array('order' => array('key' => 'ASC' ,,,) : ソート
			例： array('offset' => 10): オフセット
			例： array('limit' => 100): 件数

	【拡張について】
		個々の検索において、特殊な処理が必要な場合は
		getQuery($args)をオーバーライドしインプリメントすること
	

	【実際の実装について】*****************************************************
	$limit = 30;//1ページに表示する件数

	// 名前で検索する
	$total = $objUser->counts(array(@$_GET['word']));

	// ページャーのページ計算
	$page = (is_numeric(@$_GET['page'])) ? $_GET['page'] : 1;
	$page = min($page, ceil($total / $limit));
	$page = max($page, 1);

	// DBデータを取得
	if($total > 0){
		$args= array('word'=>@$_GET['word'],
					'limit'=> $limit,
					'offset'=> ($page - 1) * $limit,
					'order' => array('id'=> 'ASC'));
		$list = $objUser->gets($args);
	}

	$pager = getPager(sizeof($list), $total);// pagerを取得

	$smarty->assign('list', $list);
	$smarty->assign('pager', $pager);

			
******************************************************************************** */
	include_once("base.class.php");


	abstract class search extends base{
	



	////////////////////////////////////////////////////////////////////////////////
	// 検索結果をプライマリーキーで返却する
	// return array(100, 110, 120, 400);
	//
	public function getIDs($args){
	
		list($prepare, $params)= $this->getQuery($args);

		$sth= $this->mdb2->prepare('SELECT id ' . $prepare);
		$res= $sth->execute($params);

		if (PEAR::isError($res)){
			$this->error($res->getMessage());
			return false;
		}

		$list= array();
		foreach ($result as $row) {
			$list[] = $row['id'];
		}
		return $list;
	}
	////////////////////////////////////////////////////////////////////////////////
	// getQuery (protected)
	public function getQuery($args, $prepare='', $params= array()){

		$this->addWhereFilter('getWordQuery');

		return base::getQuery($args, $prepare, $params);
	}
	
	////////////////////////////////////////////////////////////////////////////////
	// getQuery の内部関数
	protected function getWordQuery($args, $prepare='', $params= array()){
	
		if (is_array($args) && array_key_exists("word", $args) && sizeof($this->search_columns) > 0){

			$word = str_replace("　", " ", $args['word']);
			$word = trim($word);
		
			// 検索クエリー生成
			$prepare.= ' AND (';
			$array = explode(" ", $word);
			for($i = 0; $i <  count($array) ; $i++){
				if($i > 0){
					$prepare .= " OR ";
				}
				
				$prepare_tmp= array();
				foreach($this->search_columns as $col){
					$prepare_tmp[]=  "(`{$col}` LIKE ?)";
					$params[] = "%" . $array[$i] . "%";
				}
				$prepare.= implode(' OR ', $prepare_tmp);

			}
			$prepare .= ") ";
		}

		return array($prepare, $params);
	}

	////////////////////////////////////////////////////////////////////////////////
}


?>