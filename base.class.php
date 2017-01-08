<?php
////////////////////////////////////////////////////////////////////////////////
/* PHPOrm Class
   Auther: Kazuna Kanajiri
   Email : disca@idiagdia.com
   This software is Free licence
*/
////////////////////////////////////////////////////////////////////////////////

abstract class base{

	var $mdb2;
	var $prefix;
	var $table_name;
	var $joinFilter= array('getJoinQuery');
	var $whereFilter= array('getColumnQuery','getOrmQuery');
	var $outputFilter= array('getOrderbyQuery','getOffsetQuery');
	var $postFilter= array(array('name'=> 'postUrlDecode', 'key'=> NULL));

	var $debug= false;
	protected $search_columns;
	////////////////////////////////////////////////////////////////////////////////

	public function error($message){
		echo $message;
	}
	//////////////////////////////////////////////////////////////////////
	public function debug($val){
		$this->debug= $val;
	}

	//////////////////////////////////////////////////////////////////////
	// $_POST で代入されるもののフィルタ
	public function addPostFilter($filter, $key){
		$this->postFilter[]= array('name' => $filter, 'key' => $key);
	}
	//////////////////////////////////////////////////////////////////////
	public function post($key, $value){
		foreach($this->postFilter as $filter){
			if (is_null($filter['key']) || $filter['key'] === $key){


				$value= call_user_func(array($this, $filter['name']), $key, $value);
			}
		}
		return $value;
	}
	public function postUrlDecode($k, $v){
		if (is_array($v)){
			return $v;
		}
		return urldecode($v);
	}
	public function postComma($k, $v){
		if (is_array($v)){
			$v= implode(',', $v);
		}
		return $v;
	}
	//////////////////////////////////////////////////////////////////////
	public function query($query){

		if ($this->debug){
			echo '<p style="background-color:#ffffee">';
			echo $query;
			echo "</p>\n";
			echo "<hr/>\n";
		}

		return $this->mdb2->query($query);
	}
	//////////////////////////////////////////////////////////////////////
	public function prepare($prepare){

		if ($this->debug){
			echo '<p style="background-color:#eeffff">';
			echo $prepare;
			echo "</p>\n";
		}
		return $this->mdb2->prepare($prepare);
	}
	public function execute($sth, $params){

		if ($this->debug){

			$e= debug_backtrace();
			foreach($e as $ee){
				echo(get_class($this) . " - " . $ee['function'] . "(file:" . $ee["file"]  . "-line:" . $ee["line"] . ")-> <br>\r\n");
			}

			echo '<p style="background-color:#ffeeff">';
			var_dump($params);
			echo "</p>\n";
			echo "<hr/>\n";
		}


		return $sth->execute($params);
	}
	//////////////////////////////////////////////////////////////////////
	private static $cachedField= array();
	function getFieldInfo($table_name= NULL)
	{
		if (is_null($table_name)){
			$table_name= $this->table_name;
		}
		
		// キャッシュデータがある場合はそれを返す
		if (isset(base::$cachedField[$table_name])){
			return base::$cachedField[$table_name];
		}

		$res = $this->query("SHOW FULL COLUMNS FROM " . $table_name);
		$list= array();
		if (PEAR::isError($res)){
			$this->error($res->getMessage());
			return false;
		}

		while (($row = $res->fetchRow())) {

			$list[$row['field']]= $row;
		}
		
		// キャッシュの再設定
		base::$cachedField[$table_name]= $list;
		
		return $list;
	}



	////////////////////////////////////////////////////////////////////////////////
	/*
		複数行を取得する関数
		
		例：
			$obj->gets(12) 
			$obj->gets(array('id' => 12))
			$obj->gets(array('orm'=> array('column'=> 'id', 'value'=> 12)))	
		上記３つは等価です。
		
	*/
	public function count($args= array()){

		if ($args["alias"] == ""){
			$args['alias']= $this->table_name;
		}
		$alias= $args["alias"];

		$params= array();
		$prepare= "SELECT COUNT(`{$alias}`.`id`) AS ct FROM {$this->table_name} ";

		list($prepare, $params)= $this->getQuery($args, $prepare, $params);

		$sth= $this->prepare($prepare);
		$res= $this->execute($sth, $params);
		if (PEAR::isError($res)){
			$this->error($res->getMessage());
			return false;
		}
		$row = $res->fetchRow();

		return intval($row["ct"]);
	}
	////////////////////////////////////////////////////////////////////////////////
	/*
		複数行を取得する関数
		
		例：
			$obj->gets(12) 
			$obj->gets(array('id' => 12))
			$obj->gets(array('orm'=> array('column'=> 'id', 'value'=> 12)))	
		上記３つは等価です。
		
	*/
	public function gets($args= array()){

		$params= array();

		if ($args["alias"] == ""){
			$args['alias']= $this->table_name;
		}
		$alias= $args["alias"];
		$select= $this->selectQuery($args);

		$prepare= "SELECT {$select} FROM {$this->table_name} ";
		
		list($prepare, $params)= $this->getQuery($args, $prepare, $params);

		$sth= $this->prepare($prepare);
		$res= $this->execute($sth, $params);
		if (PEAR::isError($res)){
			$this->error($res->getMessage());
			return false;
		}
		$list= array();
		while (($row = $res->fetchRow())) {
			$list[]= $row;
		}
		return $list;
	}

	////////////////////////////////////////////////////////////////////////////////
	/* 単一行を取得する関数 */
	public function get($args){
		
		if (!is_array($args)){
			$args= array('id' => $args);
		}
		$args['limit']= 1;

		$rows= $this->gets($args);

		if (sizeof($rows) > 0){
			return $rows[0];
		}else{
			return array();
		}
	}
	//////////////////////////////////////////////////////////////////////
	protected function selectQuery($args)
	{
		$table_name= $args["table"];
		if ($table_name == ""){
			$table_name= $this->table_name;
		}
		$info= $this->getFieldInfo($table_name);
		$alias= $args["alias"];
		if ($alias == ""){
			$alias= $table_name;
		}

		if (isset($args['select']) && is_array($args['select'])){
			$select= $args['select'];
		}else{
			$select= array_keys($info);
		}

		$columns= array();
		if (array_values($select) === @$select){
			foreach($select as $c){
				if (array_key_exists($c, $info)){
					$columns[]= "`{$alias}`.`{$c}`";
				}
			}
		}else{
			foreach($select as $c => $a){
				if (array_key_exists($c, $info)){
					$columns[]= "`{$alias}`.`{$c}` AS `{$a}`";
				}
			}
			
		}
		return implode($columns, ",");
	}
	//////////////////////////////////////////////////////////////////////
	function insertQuery($args, $values)
	{
		$alias= $args["alias"];

		$info= $this->getFieldInfo($this->table_name);
		$prepare_keys= array();
		$prepare_values= array();
		
		foreach($info as $key=> $_v){
			if ($key == "id"){
				continue;
			}
			if (array_key_exists($key, $values)){
				$prepare_keys[]= "`{$alias}`.`{$key}`";
				$prepare_values[]= "?";

				$params[]= $values[$key];
			}
		}
		$prepare.= sprintf(" (%s) VALUES (%s) ", implode($prepare_keys, ","), implode($prepare_values, ","));

		return array($prepare, $params);
	}
	////////////////////////////////////////////////////////////////////////////////
	public function insert($values){

		//配列の場合はPOSTを代入
		if (@array_values($values) === $values) {
			$tmp= $values;$values= array();
			foreach($tmp as $k){
				$values[$k]= $this->post($k, $_POST[$k]);
			}
		}

		// Aliasが指定されていない場合は table_nameをAliasに
		$args= array('alias' => $this->table_name);
		$alias= $args["alias"];

		// crated_at の付与
		$info= $this->getFieldInfo($table_name);
		if (isset($info['created_at']) && is_null($values['created_at'])){
			$values['created_at'] = date("Y-m-d H:i:s");
		}

		list($prepare, $params)= $this->insertQuery($args, $values);

		if (sizeof($params) == 0){
			return false;
		}
		$sth= $this->prepare("INSERT INTO `{$this->table_name}` {$prepare}");
		if (MDB2::isError($sth)){
			$this->error($sth->getMessage());
			return false;
		}

		$res= $this->execute($sth, $params);
		if (MDB2::isError($res)){
			return false;
		}

		$id= $this->mdb2->lastInsertId($this->table_name , 'id');

		$sth->free();
		return $id;
	}

	//////////////////////////////////////////////////////////////////////
	function save($args, $values= NULL){

		if (!is_array($args)){
			$args= array('id' => $args);
		}
		$list= $this->gets($args);
		if (sizeof($list) > 0){

			return  $this->update($args, $values);

		}else{

			return $this->insert($values);
		}
	}

	//////////////////////////////////////////////////////////////////////
	// A　args == 1 
	// B　args array(id => 1) 
	// Aと Bは等価

	public function update($args, $values, $table_name= NULL){

		if (sizeof($args) == 0){
			return false;
		}

		if (!is_array($args)){
			$args= array('id' => $args);
		}

		if ($args["alias"] == ""){
			$args['alias']= $this->table_name;
		}
		$alias= $args["alias"];

		// values 配列の場合はPOSTを代入
		if (array_values($values) === $values) {
			$tmp= $values;
			$values= array();
			foreach($tmp as $k){
				$values[$k]= $this->post($k, $_POST[$k]);
			}
		}

		// crated_at の付与
		$info= $this->getFieldInfo($table_name);
		if (isset($info['updated_at']) && is_null($values['updated_at'])){
			$values['updated_at'] = date("Y-m-d H:i:s");
		}

		// クエリの半自動生成
		list($prepare_update, $params_update)= $this->updateQuery($args, $values);
		if (sizeof($params_update) == 0){
			return false;
		}

		list($prepare_where, $params_where)= $this->getQuery($args);

		if (is_null($params_where) || (is_array($params_where) && sizeof($params_where) < 1)){
			$this->error('UPDATE ' . $this->table_name . 'SET ... WHERE args is Empty');
			return false;
		}

		// WHERE をつけてPrepare & Excute
		$params = array_merge($params_update, $params_where);

		$sth = $this->prepare("UPDATE `{$this->table_name}` SET {$prepare_update} {$prepare_where}");
		if (MDB2::isError($sth)){
			$this->error($sth->getMessage());
			return false;
		}

		$res= $this->execute($sth,$params);

		if (MDB2::isError($res)){
			$this->error($res->getMessage());
			return false;
		}

		$sth->free();

		return true;
	}
	//////////////////////////////////////////////////////////////////////
	protected function updateQuery($args, $values)
	{
		if ($args["alias"] == ""){
			$args['alias']= $this->table_name;
		}
		$alias= $args["alias"];


		$info= $this->getFieldInfo($table_name);
		$pre= array();
		$params= array();
		
		foreach($info as $key=> $value){
		
			if ($key == "id"){
				continue;
			}
			if (array_key_exists($key, $values)){
				$pre[]= " `{$alias}`.`{$key}` = ? ";
				$params[]= $values[$key];
			}
		}
		$prepare= " " . implode($pre, ",") . " ";

		return array($prepare, $params);
	}
	////////////////////////////////////////////////////////////////////////////////
	public function delete($args){
	

		list($prepare, $params)= $this->getQuery($args);

		// パラメータがゼロの場合（DELETE FROM table WHERE 1）になるのでは実行させない
		if (is_null($params) || (is_array($params) && sizeof($params) < 1)){
			$this->error('DELETE FROM [' . $this->table_name . '] WHERE args is Empty');
			return false;
		}

		$sth= $this->prepare('DELETE FROM  ' . $this->table_name . ' ' . $prepare);
		$res= $this->execute($sth, $params);
		if (PEAR::isError($res)){
			$this->error($res->getMessage());
			return false;
		}

		$sth->free();

        return true;
	}
	////////////////////////////////////////////////////////////////////////////////
	// 検索結果件数を返却する
	public function number($func= 'COUNT', $column= 'id', $args= array()){
		
		switch (strtoupper($func)){
			case 'MIN': $func= 'MIN'; break;
			case 'MAX': $func= 'MAX'; break;
			default: $func= 'COUNT';
		}

		// column が存在しているか確認
		$info= $this->getFieldInfo();
		if (! isset($info[$column])){
			return false;
		}

		list($prepare, $params)= $this->getQuery($args);

		$sth= $this->prepare('SELECT ' . $func .'(`' . $column . '`) AS cnt FROM ' . $this->table_name . ' '. $prepare);
		$res= $this->execute($sth, $params);

		if (PEAR::isError($res)){
			$this->error($res->getMessage());
			return false;
		}
		$row = $res->fetchRow();
		$sth->free();

		return intval($row['cnt']);
	}

	////////////////////////////////////////////////////////////////////////////////
	// getQuery に渡すクエリフィルターの追加
	public function addWhereFilter($funcName){

		$this->whereFilter[]= $funcName;

		return true;
	}
	////////////////////////////////////////////////////////////////////////////////
	// getQuery に渡すクエリフィルターの追加
	public function removeWhereFilter($funcName){
		
		$whereFilter= array();
		foreach($this->whereFilter as $v){
			if ($v != $funcName){
				$whereFilter[]= $v;
			}
		}
		$this->whereFilter= $whereFilter;

		return true;
	}
	////////////////////////////////////////////////////////////////////////////////
	public function getQuery($args, $prepare='', $params=array()){

		foreach($this->joinFilter as $v){
			list($prepare, $params)= call_user_func(array($this, $v), $args, $prepare, $params);
		}

		$prepare.= ' WHERE 1 ';

		foreach($this->whereFilter as $v){

			list($prepare, $params)= call_user_func(array($this, $v), $args, $prepare, $params);
		}

		// Order by, Offset 等を取得する
		foreach($this->outputFilter as $v){

			list($prepare, $params)= call_user_func(array($this, $v), $args, $prepare, $params);
		}

		return array($prepare, $params);
	}
	////////////////////////////////////////////////////////////////////////////////
	// getQuery の内部関数(簡易ORマッパーを使わないで簡単にSELECTできる関数)
	/*
		例：id= 123 (IDのみ)
			string:123

		例：id = '123' 
			array('id' => '123')
			
		例：tel = '0120333906'
			array('tel' => '0120333906')
			
			
		例：tel='0120333906' AND fax = '0120333906'
			array('tel' => '0120333906', 'fax' => '0120333906')
		
	*/
	protected function getColumnQuery($args, $prepare='', $params= array()){

		if (!is_array($args)){
			$args= array('id' => $args);
		}
		$alias= $args['alias'];

		// テーブル定義のカラム名一覧取得
		$info= $this->getFieldInfo($this->table_name);

		$keys= array_keys($args);
		foreach($args as $k => $v){

			// 念のためトリミング
			$k= trim($k);

			// テーブルのカラム名と $argsの連想配列キーが一致した場合
			if (isset($info[$k]) && $k == $info[$k]['field']){

				$column= $info[$k]['field']; // $k == $colum ですが、入力パラメータの値をクエリーに直接代入するのは怖いので念のため

				if (is_array($v)){
					$prepare.= " AND `{$alias}`.`{$column}` IN (" . substr(str_repeat(",?", count($v)), 1) . ")";
					$params = array_merge($params, $v);
				}elseif ($v === NULL){
					$prepare.= " AND `{$alias}`.`{$column}` IS NULL";
					
				}else{
					$prepare.= " AND `{$alias}`.`{$column}` = ?";
					$params[]= $v;
					
				}
			}
		}
		
		return array($prepare, $params);
	}

	////////////////////////////////////////////////////////////////////////////////
	// getQuery の内部関数 （簡易ORマッパー）
	/*
	
		例：key > 5 の場合
		array('formula' => '>', 'column' => 'key', 'value' => 5)
		
		例：(key > 4 OR (aaa = 4)) AND b = 5 
		array('formula' => '>', 'column' => 'key', 'value' => 5 , 
		'or'=> array('formula' => '=', 'column' => 'aaa', 'value' => 4  ), 
		array('formula' => '=', 'column' => 'b', 'value' => 5)
		
		例： key1 > key2 の場合
		array('formula' => '>' column=>array('key1', 'key2'))
	
	
	*/
	protected function getOrmQuery($args, $prepare='', $params= array()){
	
		$orm= NULL;

		if (isset($args['orm']) && is_array($args['orm'])){
			$orm= $args['orm'];
		}else if (isset($args['column']) && isset($args['formula'])){
			// key に column と formula がある場合は自動的にORMとする。そのためcolumn,formulaは予約語
			$orm= $args;
		}

		if ($orm){
			if (array_values($orm) === @$orm){
					// 配列(AND結合されている)
			}else{
				// 連想配列
				$orm= array($orm);
			}
			foreach($orm as $cmp){	
				if ($cmp['alias'] == ""){
					$cmp["alias"]= $args['alias'];
				}
				list($prepare, $params)= $this->getOrmQueryInner($cmp, $prepare, $params);
			}
		}

		return array($prepare, $params);
	}
	private function getOrmQueryInner($orm, $prepare, $params){
	
	
		// 論理演算子
		$logical= 'AND';
		switch (strtoupper($orm['logical'])){
			case 'AND': $logical= 'AND'; break;
			case 'OR': $logical= 'OR'; break;
		}
		
		$column= $orm['column'];
		$alias= $orm['alias'];


		// 比較演算子
		// 念のためFormulaの直接代入は避けておく
		$formula= '=';
		switch ($orm['formula']){
			case '=': $formula= '='; break;
			case '<': $formula= '<'; break;
			case '<=': $formula= '<='; break;
			case '>': $formula= '>'; break;
			case '>=': $formula= '>='; break;
			case '!=': $formula= '!='; break;
			case 'LIKE': $formula= 'LIKE'; break;
			case 'IS NOT NULL': $formula= 'IS NOT NULL';break;
			case 'IN': $formula= 'IN';break;
			case 'IS NULL': $formula= 'IS NULL';break;
			case 'INSET': $formula= 'FIND_IN_SET';break;
			case 'MATCH': $formula= 'MATCH';break;
			default:break;
		}

		switch ($formula){
			case '=':
			case '<':
			case '<=':
			case '>': 
			case '>=':
			case '!=':
			case 'LIKE':
				// 通常の不等式
				if (is_array($column) && is_array($alias)){
					// 値ではなくてカラムの場合
					$column1= $column[0];
					$column2= $column[1];
					$alias1= $alias[0];
					$alias2= $alias[1];
					$prepare.= " {$logical} (`{$alias1}`.`{$column1}` {$formula} `{$alias2}`.`{$column2}` ";
	
				}else{
					$prepare.= " {$logical} (`{$alias}`.`{$column}` {$formula} ? ";
					$params[]= $orm['value'];
				}
				break;
			case 'IS NOT NULL':
				$prepare.= " {$logical} (`{$alias}`.`{$column}` IS NOT NULL";
				
				break;			
			case 'IS NULL':
				$prepare.= " {$logical} (`{$alias}`.`{$column}` IS NULL";
				
				break;
			case 'FIND_IN_SET':
				if (! is_array($orm['value'])){
					$orm['value']= array($orm['value']);
				}
				$tmp= array();
				foreach($orm['value'] as $v){
					$tmp[]= "FIND_IN_SET(?, `{$alias}`.`{$column}`)";
					$params[]= $v;
				}
				$prepare.= " {$logical} (" . implode('OR', $tmp);
				break;
			case 'IN':
				if (! is_array($orm['value'])){
					$orm['value']= array($orm['value']);
				}
				$tmp= array();
				foreach($orm['value'] as $v){
					$tmp[]= "?";
					$params[]= $v;
				}
				$prepare.= " {$logical} (`{$alias}`.`{$column}` IN (" . implode(',', $tmp) .")";
				break;

			case 'MATCH':
				if (! is_array($orm['value'])){
					$orm['value']= array($orm['value']);
				}
				$tmp= array();
				foreach($orm['value'] as $v){
					$tmp[]= "(MATCH(`{$alias}`.`{$column}`) AGAINST(? in boolean mode))";
					$params[]= $v;
				}
				$prepare.= " {$logical} (" . implode('OR', $tmp);
				break;
			default:break;
		}
		
		
		// OR の場合
		if (isset($orm['OR']) && is_array($orm['OR'])){

			if ($orm['OR']['alias'] == ""){
				$orm['OR']["alias"]= $orm['alias'];
			}

			$orm['OR']['logical']= 'OR';
			list($prepare, $params)= $this->getOrmQueryInner($orm['OR'], $prepare, $params);
		}
		$prepare.= ")"; //OR論理演算子のために後で閉じ括弧を付ける

		return array($prepare, $params);
	}
	////////////////////////////////////////////////////////////////////////////////
	// getJoinQuwery の内部関数
	protected function getJoinQuery($args, $prepare='', $params= array()){

		$join= NULL;
		if (isset($args['join']) && is_array($args['join'])){
			$join= $args['join'];
		}
		if ($join){
			if (array_values($join) === @$join){
				// 配列格納 array(array(join1), array(join2))
				// 望ましい形状なので問題なし
			}else{
				// 連想配列
				// array(join1)を array(array(join1)) に成型
				$join= array($join);
			}
			foreach($join as $orm){
				list($prepare, $params)= $this->getJoinQueryInner($orm, $prepare, $params);
			}
		}
		return array($prepare, $params);
	}
	protected function getJoinQueryInner($args, $prepare='', $params= array()){

		$prp= '';
		$pams= array();


		if (! is_array($args['alias'])){
			$args['alias']= array($this->table_name, $args['alias']);
		}
		if (! is_array($args['column'])){
			$args['column']= array('id', $args['column']);
		}

		list($prp, $pams)= $this->getColumnQuery($args, $prp, $pams);
		list($prp, $pams)= $this->getOrmQueryInner($args, $prp, $pams);

		$join_table= $args['table'];
		$join_alias= $args['alias'][1];
		$join_select= $this->selectQuery(array(
			'table'=> $join_table, 
			'alias'=> $join_alias, 
			'select' => $args['select']));

		$prepare= str_replace("FROM", ", {$join_select} FROM", $prepare);

		$prepare.= "LEFT JOIN `{$join_table}` AS `{$join_alias}` ON (1 {$prp})";
		
		$params= array_merge($params, $pams);

		return array($prepare, $params);

	}
	////////////////////////////////////////////////////////////////////////////////
	// getQuery の内部関数
	protected function getOrderbyQuery($args, $prepare='', $params= array()){

		// order by の追加
		if (is_array(@$args['order']) && sizeof($args['order'] > 0)){

			$alias= $args['alias'];
			$prepare.= ' ORDER BY ';
			$orders= array();
			
			foreach ($args['order'] as $k => $v){
				$orderBy= 'ASC';
				if (strtoupper($v) == 'DESC'){$orderBy= 'DESC';}

				$prefix= '';
				$orders[]= sprintf("`{$alias}`.`%s` %s ", $k, $orderBy); 
			}
			$prepare.= implode(',', $orders);
		}

		return array($prepare, $params);
	}
	////////////////////////////////////////////////////////////////////////////////
	// getQuery の内部関数
	protected function getOffsetQuery($args, $prepare='', $params= array(), $alias=''){

		if (is_array($args) && (isset($args['offset']) || isset($args['limit']))){

			$prepare.= ' LIMIT ? OFFSET ?';
			$params[]= intval($args['limit']);
			$params[]= intval($args['offset']);
		}
		return array($prepare, $params);
	}
	////////////////////////////////////////////////////////////////////////////////
	
}


?>