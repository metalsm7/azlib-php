<?php
namespace AZLib {
use AZLib\{AZData, AZList};
class AZSql {
  //
  private $_db; // string
  private $_query; // string
  private $_compiled_query; // 실제 요청시 사용될 쿼리문
  private $_parameters; // AZData
  private $_prepared_parameter_types; // array, prepared statement 사용시 사용될 parameters 의 type 목록
  private $_prepared_parameter_keys; // array, prepared statement 사용시 사용될 parameters 의 key 목록
  private $_return_parameters; // AZData, out 반환값 설정 자료
  private $_identity = false;
  private $_is_transaction = false;
  private $_transaction_result;
  private $_action_tran_on_commit;
  private $_action_tran_on_rollback;
  private $_is_stored_procedure = false;
  private $_is_prepared = false;
  //
  public function __construct(&$db = null) {
    //
    if (!is_null($db)) {
      $this->_db = $db;
    }
    //
    $this->_parameters = AZData::create();
  }

  public static function create(&$db = null) {
    return new AZSql($db);
  }

  /**
   * 연결 객체에 대한 class 명을 통해 mysqli 여부 반환
   * @return boolean
   */
  protected function is_mysqli() {
    return get_class($this->_db->conn_id) == 'mysqli';
  }
  
  /**
   * mysqli 객체를 반환
   */
  protected function get_mysqli() {
    return $this->is_mysqli() ? $this->_db->conn_id : null;
  }

  /**
   * 쿼리 생성을 위한 인수값에 대한 변경 처리, prepared statement를 사용하지 않는 경우 처리
   * @param $query string 변경할 쿼리 문자열
   * @param $key string
   * @param $value mixed 
   * @return string
   */
  private function param_replacer(string $query, string $key, $value) {
    $query = preg_replace("/{$key}$/", $this->_db->escape($value), $query);
    $query = preg_replace("/{$key}\r\n/", $this->_db->escape($value)."\r\n", $query);
    $query = preg_replace("/{$key}\n/", $this->_db->escape($value)."\n", $query);
    $query = preg_replace("/{$key}\s/", $this->_db->escape($value)." ", $query);
    $query = preg_replace("/{$key}\t/", $this->_db->escape($value)."\t", $query);
    $query = preg_replace("/{$key},/", $this->_db->escape($value).",", $query);
    $query = preg_replace("/{$key}\)/", $this->_db->escape($value).")", $query);
    $query = preg_replace("/{$key};/", $this->_db->escape($value).";", $query);
    return $query;
  }

  /**
   * 특정 양식의 쿼리문에 대해 prepared statement 사용을 위한 전달값 생성 및 반환
   */
  private function prepared_query_param_replacer(string &$query, $data) {
    // 반환자료 생성
    $rtn_val = array('types' => array(), 'values' => array());
    //
    foreach ($data as $key => $value) {
      $replace_cnt = 0;
      $query = preg_replace_callback("/{$key}$/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'; }, $query);
      $query = preg_replace_callback("/{$key}\r\n/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'."\r\n"; }, $query);
      $query = preg_replace_callback("/{$key}\n/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'."\n"; }, $query);
      $query = preg_replace_callback("/{$key}\s/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'." "; }, $query);
      $query = preg_replace_callback("/{$key}\t/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'."\t"; }, $query);
      $query = preg_replace_callback("/{$key},/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'.","; }, $query);
      $query = preg_replace_callback("/{$key}\)/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'.")"; }, $query);
      $query = preg_replace_callback("/{$key};/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'.";"; }, $query);
      //
      for ($i = 0; $i < $replace_cnt; $i++) {
        $type_str = 's';
        switch (gettype($data[$key])) {
          case 'integer':
            $type_str = 'i';
            break;
          case 'float':
          case 'double':
            $type_str = 'd';
            break;
          default:
            $type_str = 's';
            break;
        }
        array_push($rtn_val['types'], $type_str);
        array_push($rtn_val['values'], $data[$key]);
      }
    }
    return $rtn_val;
  }

  /**
   * 특정 양식의 쿼리문에 대해 prepared statement 사용을 위한 쿼리문으로 변경 처리
   */
  private function prepared_query_replacer(string &$query, $data) {
    // 반환자료 생성
    $rtn_val = array('types' => array(), 'keys' => array());
    //
    foreach ($data as $key => $value) {
      $replace_cnt = 0;
      $query = preg_replace_callback("/{$key}$/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'; }, $query);
      $query = preg_replace_callback("/{$key}\r\n/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'."\r\n"; }, $query);
      $query = preg_replace_callback("/{$key}\n/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'."\n"; }, $query);
      $query = preg_replace_callback("/{$key}\s/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'." "; }, $query);
      $query = preg_replace_callback("/{$key}\t/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'."\t"; }, $query);
      $query = preg_replace_callback("/{$key},/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'.","; }, $query);
      $query = preg_replace_callback("/{$key}\)/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'.")"; }, $query);
      $query = preg_replace_callback("/{$key};/", function ($match) use (&$replace_cnt) { $replace_cnt++; return '?'.";"; }, $query);
      //
      for ($i = 0; $i < $replace_cnt; $i++) {
        $type_str = 's';
        switch (gettype($data[$key])) {
          case 'integer':
            $type_str = 'i';
            break;
          case 'float':
          case 'double':
            $type_str = 'd';
            break;
          default:
            $type_str = 's';
            break;
        }
        array_push($rtn_val['types'], $type_str);
        array_push($rtn_val['keys'], $key);
      }
    }
    return $rtn_val;
  }

  /**
   * prepared statement 를 사용하지 않은 경우에 한하여, 요청시 컬럼정보의 type에 따른 반환값에 대한 타입 캐스팅 처리
   */
  private function value_caster(&$value, $type) {
    switch ($type) {
      case 1:
      case 2:
      case 3:
      case 8:
      case 9:
      case 16:
        $value = intval($value);
        break;
      case 4:
        $value = floatval($value);
        break;
      case 5:
      case 246:
        $value = doubleval($value);
        break;
    }
  }

  /**
   * prepared statement 를 사용하지 않은 경우에 한하여, 요청시 컬럼정보의 type에 따른 반환값에 대한 타입 캐스팅 처리
   */
  private function data_value_caster(array &$data, $metadata) {
    foreach ($data as $key => &$value) {
      foreach ($metadata as $meta) {
        if ($meta->name === $key) {
          $this->value_caster($value, $meta->type);
          break;
        }
      }
    }
  }

  /**
   * method overloading 을 위한 처리(magic method)
   */
  public function __call($name, $args) {
    switch ($name) {
      case 'execute':
      case 'get':
      case 'get_data':
      case 'get_list':
      case 'get_multi':
        switch (count($args)) {
          case 0:
            return call_user_func_array(array($this, $name), array(false));
          case 1:
            return call_user_func_array(array($this, $name), $args);
          case 2:
            if (gettype($args[1]) === 'boolean') {
              return call_user_func_array(array($this, "{$name}_with_query"), $args);
            } else {
              return call_user_func_array(array($this, "{$name}_with_params"), $args);
            }
            // no break
          case 3:
            return call_user_func_array(array($this, "{$name}_with_params"), $args);
        }
        break;
    }
  }

  public function set_query($query) {
    //
    $this->_query = $query;
    $this->_compiled_query = null;
    //
    $this->_prepared_parameter_types = null;
    $this->_prepared_parameter_keys = null;
    //
    return $this;
  }

  public function get_query(): string {
    return $this->_query;
  }

  public function clear_query() {
    //
    $this->_query = null;
    $this->_compiled_query = null;
    //
    $this->_prepared_parameter_types = null;
    $this->_prepared_parameter_keys = null;
    //
    return $this;
  }

  public function get_compiled_query(): string {
    if ($this->is_prepared()) {
      $this->compile_prepared();
    }
    else {
      $this->compile();
    }
    return $this->_compiled_query;
  }

  protected function compile() {
    if ($this->_compiled_query) return;
    //
    $query = $this->get_query();
    $params = $this->get_parameters();
    if (gettype($params) === 'object' && $params instanceof AZData) {
      $params = $params->to_json();
    }
    foreach ($params as $key => $value) {
      $query = $this->param_replacer($query, $key, $value);
    }
    //
    $this->_compiled_query = $query;
    //
    return $this;
  }

  protected function compile_prepared() {
    //
    if (
        !$this->_compiled_query ||
        !$this->_prepared_parameter_types ||
        !$this->_prepared_parameter_keys
      ) {
      $query = $this->get_query();
      $params = $this->get_parameters();
      if (gettype($params) === 'object' && $params instanceof AZData) {
        $params = $params->to_json();
      }
      $result = $this->prepared_query_replacer($query, $params);
      $this->_compiled_query = $query;
      $this->_prepared_parameter_types = $result['types'];
      $this->_prepared_parameter_keys = $result['keys'];
    }
    //
    return $this;
  }

  public function set_parameters($params) {
  // public function set_parameters(&$params) {
    if (gettype($params) === 'array') {
      $this->_parameters = AZData::parse($params);
    }
    else {
      $this->_parameters = $params;
    }
    /*
    if (gettype($params) === 'object' && $params instanceof AZData) {
      $this->_parameters = $params->to_json();
    }
    */
    return $this;
  }

  public function get_parameters() {
    return $this->_parameters;
  }

  public function get_parameter(string $key) {
    return $this->_parameters->get($key);
  }

  public function add_parameter(string $key, $value) {
    if (!$this->is_prepared()) $this->_compiled_query = null;
    $this->_parameters->add($key, $value);
    return $this;
  }

  public function remove_parameter(string $key) {
    if (!$this->is_prepared()) $this->_compiled_query = null;
    $this->_parameters->remove($key);
    return $this;
  }

  public function clear_parameters() {
    if (!$this->is_prepared()) $this->_compiled_query = null;
    $this->_parameters->clear();
    return $this;
  }

  public function set_return_parameters(AZData &$params) {
    $this->_return_parameters = $params;
    return $this;
  }

  public function get_return_parameters() {
    return $this->_return_parameters;
  }

  public function get_return_parameter(string $key) {
    return $this->_return_parameters->get($key);
  }

  public function add_return_parameter(string $key, $value) {
    if (!$this->is_prepared()) $this->_compiled_query = null;
    if (is_null($this->_return_parameters)) {
      $this->_return_parameters = AZData::create();
    }
    $this->_return_parameters->add($key, $value);
    return $this;
  }

  public function update_return_parameter(string $key, $value) {
    if (!is_null($this->_return_parameters)) {
      $this->_return_parameters->set($key, $value);
    }
    return $this;
  }

  public function remove_return_parameter(string $key) {
    if (!$this->is_prepared()) $this->_compiled_query = null;
    if (is_null($this->_return_parameters)) {
      $this->_return_parameters->remove($key);
    }
    return $this;
  }

  public function clear_return_parameters() {
    if (!$this->is_prepared()) $this->_compiled_query = null;
    $this->_return_parameters = null;
    return $this;
  }

  public function set_prepared($state) {
    //
    $this->_is_prepared = $state;
    //
    $this->_compiled_query = null;
    //
    $this->_prepared_parameter_types = null;
    $this->_prepared_parameter_keys = null;
    //
    return $this;
  }

  public function is_prepared() {
    return $this->_is_prepared;
  }

  public function set_stored_procedure($state) {
    $this->_is_stored_procedure = $state;
    return $this;
  }

  public function is_stored_procedure() {
    return $this->_is_stored_procedure;
  }

  public function clear() {
    // private $_query; // string
    // private $_compiled_query; // 실제 요청시 사용될 쿼리문
    // private $_parameters; // AZData
    // private $_prepared_parameter_types; // array, prepared statement 사용시 사용될 parameters 의 type 목록
    // private $_prepared_parameter_keys; // array, prepared statement 사용시 사용될 parameters 의 key 목록
    // private $_return_parameters; // AZData, out 반환값 설정 자료

    //
    $this->clear_query();
    $this->clear_parameters();
    $this->clear_return_parameters();
    //
    $this->_identity = false;
    $this->_is_transaction = false;
    $this->_transaction_result;
    $this->_action_tran_on_commit;
    $this->_action_tran_on_rollback;
    $this->_is_stored_procedure = false;
    $this->_is_prepared = false;
    //
    return $this;
  }

  public function begin_tran(callable $on_commit, callable $on_rollback) {
    $this->get_mysqli()->autocommit(false);
    //
    $this->_is_transaction = true;
    //
    if ($on_commit) $this->_action_tran_on_commit = $on_commit;
    if ($on_rollback) $this->_action_tran_on_rollback = $on_rollback;
  }

  public function commit() {
    $is_success = true;
    try {
      $this->get_mysqli()->commit();
    }
    catch (Exception $ex) {
      $this->get_mysqli()->rollback();
      $is_success = false;
    }
    finally {
      $this->get_mysqli()->autocommit(true);
    }
    //
    if ($is_success && $_action_tran_on_commit) {
      $_action_tran_on_commit();
    }
    else if (!$is_success && $_action_tran_on_rollback) {
      $_action_tran_on_commit();
    }
  }

  protected function execute(bool $identity = false): int {
    //
    $rtn_val = 0;
    //
    if ($this->is_prepared()) {
      // prepared statement 사용의 경우

      $this->compile_prepared();
      //
      $stmt = $this->get_mysqli()->prepare($this->_compiled_query);
      if (!$stmt) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      if ($stmt && $this->_db->error() && $this->_db->error()->code != 0) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      //
      if (
          $this->get_parameters() && 
          $this->get_parameters()->size() > 0 &&
          $this->_prepared_parameter_types && 
          $this->_prepared_parameter_keys
        ) {
        $types = join('', $this->_prepared_parameter_types);
        $values = array();
        foreach ($this->_prepared_parameter_keys as $key) {
          array_push($values, $this->get_parameter($key));
        }
        $stmt->bind_param($types, ...$values);
      }
      //
      if ($stmt->execute()) {
        if (gettype($result) == 'object') {
          $result->free_result();
        }
        //
        $rtn_val = $identity ? $stmt->insert_id() : $stmt->affected_rows();
        //
        // $idx = 0;
        while ($stmt->more_results() && $stmt->next_result()) {
          if ($result = $stmt->get_result()) {
            $result->free_result();
          }
        }
        //
        $stmt->free_result();
        $stmt->close();
      }
    }
    else {
      // prepared statement 가 아닌 일반 사용의 경우

      $this->compile();
      //
      $result = $this->_db->query($this->_compiled_query);
      if ($result == false) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      if ($result && $this->_db->error() && $this->_db->error()->code != 0) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      $rtn_val = $identity ? $this->_db->insert_id() : $this->_db->affected_rows();
      //
      if (gettype($result) == 'object') {
        $this->free_results($result);
        $result->free_result();
      }
    }
    //
    return $rtn_val;
  }

  protected function execute_with_query(string $query, bool $identity = false): int {
    //
    $this->set_query($query);
    $this->clear_parameters();
    //
    return $this->execute($identity);
  }

  protected function execute_with_params(string $query, $params, bool $identity = false): int {
    //
    $this->set_query($query);
    $this->set_parameters($params);
    //
    return $this->execute($identity);
  }

  protected function get($type_cast = false) {
    //
    return $this->get_data($type_cast)->get(0);
  }

  protected function get_with_query(string $query, $type_cast = false) {
    //
    $this->set_query($query);
    $this->clear_parameters();
    //
    return $this->get($type_cast);
  }

  protected function get_with_params(string $query, $params, $type_cast = false) {
    //
    $this->set_query($query);
    $this->set_parameters($params);
    //
    return $this->get($type_cast);
  }

  protected function get_data($type_cast = false): AZData {
    //
    $rtn_val = AZData::create();
    //
    if ($this->is_prepared()) {
      // prepared statement 사용의 경우

      $this->compile_prepared();
      //
      $stmt = $this->get_mysqli()->prepare($this->_compiled_query);
      if (!$stmt) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      if ($stmt && $this->_db->error() && $this->_db->error()->code != 0) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      //
      if (
          $this->get_parameters() && 
          $this->get_parameters()->size() > 0 &&
          $this->_prepared_parameter_types && 
          $this->_prepared_parameter_keys
        ) {
        $types = join('', $this->_prepared_parameter_types);
        $values = array();
        foreach ($this->_prepared_parameter_keys as $key) {
          array_push($values, $this->get_parameter($key));
        }
        $stmt->bind_param($types, ...$values);
      }
      //
      if ($stmt->execute()) {
        $result = $stmt->get_result();
        $fields = array();
        while ($field = $result->fetch_field()) {
          array_push($fields, $field->name);
        }
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
          //
          $idx = 0;
          foreach ($fields as $field) {
            $rtn_val->add($field, $row[$idx]);
            $idx++;
          }
        }
        $result->free_result();
        //
        while ($stmt->more_results() && $stmt->next_result()) {
          if ($result = $stmt->get_result()) {
            $result->free_result();
          }
        }
        //
        $stmt->free_result();
        $stmt->close();
      }
    }
    else {
      // prepared statement 가 아닌 일반 사용의 경우

      $this->compile();
      //
      $result = $this->_db->query($this->_compiled_query);
      if (!$result) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      if ($result && $this->_db->error() && $this->_db->error()->code != 0) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      $data = $result->row_array();
      if ($type_cast) {
        $field = $result->field_data();
        $this->data_value_caster($data, $field);
        unset($field);
      }
      //
      $rtn_val = AZData::parse($data);
      //
      unset($params);
      unset($type_cast);
      $this->free_results($result);
      $result->free_result();
    }

    //
    if (
        $this->is_stored_procedure() &&
        !is_null($this->get_return_parameters()) &&
        $this->get_return_parameters()->size() > 0
      ) {
      //
      $query = 'SELECT';
      //
      $idx = 0;
      $keys = $this->get_return_parameters()->keys();
      foreach ($keys as $key) {
        $query .= ($idx > 0 ? ', ' : ' ').$key.' AS '.str_replace('@', 'o_', $key);
        $idx++;
      }
      $result = $this->get_mysqli()->query($query);
      if ($result && $result->num_rows > 0) {
        //
        $fields = array();
        while ($field = $result->fetch_field()) {
          $fields[$field->name] = $field->type;
        }
        //
        $result->data_seek(0);
        //
        $data = $result->fetch_array(MYSQLI_ASSOC);
        $result->free_result();
        //
        foreach ($keys as $key) {
          $key_mod = str_replace('@', 'o_', $key);
          $value = $data[$key_mod];
          $this->value_caster($value, $fields[$key_mod]);
          $this->update_return_parameter($key, $value);
        }
        //
        $data = null;
      }
    }
    //
    return $rtn_val;
  }

  /**
   * 지정된 쿼리 문자열에 대한 단일 행 결과를 AZData 객체로 반환
   * @param string $query 쿼리 문자열
   * @param bool $type_cast = false 결과값을 DB의 자료형에 맞춰서 type casting 할지 여부
   * @return AZData
   */
  protected function get_data_with_query(string $query, $type_cast = false): AZData {
    //
    $this->set_query($query);
    $this->clear_parameters();
    //
    return $this->get_data($type_cast);
  }

  /**
   * 지정된 쿼리 문자열에 대한 단일 행 결과를 AZData 객체로 반환
   * @param string $query 쿼리 문자열
   * @param AZData|array $params 쿼리 문자열에 등록된 대체 문자열 자료
   * @param bool $type_cast = false 결과값을 DB의 자료형에 맞춰서 type casting 할지 여부
   * @return AZData
   */
  protected function get_data_with_params(string $query, $params, $type_cast = false): AZData {
    //
    $this->set_query($query);
    $this->set_parameters($params);
    //
    return $this->get_data($type_cast);
  }

  /**
   * 지정된 쿼리 문자열에 대한 다행 결과를 AZList 객체로 반환
   */
  protected function get_list($type_cast = false): AZList {
    $rtn_val = AZList::create();
    //
    if ($this->is_prepared()) {
      // prepared statement 사용의 경우

      $this->compile_prepared();
      //
      $stmt = $this->get_mysqli()->prepare($this->_compiled_query);
      if (!$stmt) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      if ($stmt && $this->_db->error() && $this->_db->error()->code != 0) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      //
      if (
          $this->get_parameters() && 
          $this->get_parameters()->size() > 0 &&
          $this->_prepared_parameter_types && 
          $this->_prepared_parameter_keys
        ) {
        $types = join('', $this->_prepared_parameter_types);
        $values = array();
        foreach ($this->_prepared_parameter_keys as $key) {
          array_push($values, $this->get_parameter($key));
        }
        $stmt->bind_param($types, ...$values);
      }
      //
      if ($stmt->execute()) {
        $result = $stmt->get_result();
        $fields = array();
        while ($field = $result->fetch_field()) {
          array_push($fields, $field->name);
        }
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
          $rtn_data = AZData::create();
          //
          $idx = 0;
          foreach ($fields as $field) {
            $rtn_data->add($field, $row[$idx]);
            $idx++;
          }
          //
          $rtn_val->add($rtn_data);
        }
        $result->free_result();
        //
        while ($stmt->more_results() && $stmt->next_result()) {
          if ($result = $stmt->get_result()) {
            $result->free_result();
          }
        }
        //
        $stmt->free_result();
        $stmt->close();
      }
    }
    else {
      // prepared statement 가 아닌 일반 사용의 경우

      $this->compile();
      //
      $result = $this->_db->query($this->_compiled_query);
      if (!$result) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      if ($result && $this->_db->error() && $this->_db->error()->code != 0) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      $list = $result->result_array();
      $field = null;
      if ($type_cast) {
        $field = $result->field_data();
      }
      foreach ($list as $data) {
        if ($type_cast) {
          $this->data_value_caster($data, $field);
        }
        $rtn_val->add(AZData::parse($data));
      }
      //
      unset($field);
      unset($type_cast);
      $this->free_results($result);
      $result->free_result();
    }

    //
    if (
        $this->is_stored_procedure() &&
        !is_null($this->get_return_parameters()) &&
        $this->get_return_parameters()->size() > 0
      ) {
      //
      $query = 'SELECT';
      //
      $idx = 0;
      $keys = $this->get_return_parameters()->keys();
      foreach ($keys as $key) {
        $query .= ($idx > 0 ? ', ' : ' ').$key.' AS '.str_replace('@', 'o_', $key);
        $idx++;
      }
      $result = $this->get_mysqli()->query($query);
      if ($result && $result->num_rows > 0) {
        //
        $fields = array();
        while ($field = $result->fetch_field()) {
          $fields[$field->name] = $field->type;
        }
        //
        $result->data_seek(0);
        //
        $data = $result->fetch_array(MYSQLI_ASSOC);
        $result->free_result();
        //
        foreach ($keys as $key) {
          $key_mod = str_replace('@', 'o_', $key);
          $value = $data[$key_mod];
          $this->value_caster($value, $fields[$key_mod]);
          $this->update_return_parameter($key, $value);
        }
        //
        $data = null;
      }
    }
    //
    return $rtn_val;
  }
  
  protected function get_list_with_query(string $query, $type_cast = false): AZList {
    //
    $this->set_query($query);
    $this->clear_parameters();
    //
    return $this->get_list($type_cast);
  }

  protected function get_list_with_params(string $query, $params, $type_cast = false): AZList {
    //
    $this->set_query($query);
    $this->set_parameters($params);
    //
    return $this->get_list($type_cast);
  }

  protected function get_multi($type_cast = false): array {
    //
    $rtn_val = array();
    //
    if ($this->is_prepared()) {
      // prepared statement 사용의 경우

      $this->compile_prepared();
      //
      $stmt = $this->get_mysqli()->prepare($this->_compiled_query);
      if (!$stmt) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      if ($stmt && $this->_db->error() && $this->_db->error()->code != 0) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      //
      if (
          $this->get_parameters() && 
          $this->get_parameters()->size() > 0 &&
          $this->_prepared_parameter_types && 
          $this->_prepared_parameter_keys
        ) {
        $types = join('', $this->_prepared_parameter_types);
        $values = array();
        foreach ($this->_prepared_parameter_keys as $key) {
          array_push($values, $this->get_parameter($key));
        }
        $stmt->bind_param($types, ...$values);
      }
      //
      if ($stmt->execute()) {
        //
        while (
            ($result = $stmt->get_result()) ||
            ($stmt->more_results() && $stmt->next_result() && $result = $stmt->get_result())
          ) {
          $fields = array();
          while ($field = $result->fetch_field()) {
            array_push($fields, $field->name);
          }
          $list = AZList::create();
          while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $rtn_data = AZData::create();
            //
            $idx = 0;
            foreach ($fields as $field) {
              $rtn_data->add($field, $row[$idx]);
              $idx++;
            }
            //
            $list->add($rtn_data);
          }
          array_push($rtn_val, $list);
          $result->free_result();
        }
        //
        $stmt->free_result();
        $stmt->close();
      }
    }
    else {
      // prepared statement 가 아닌 일반 사용의 경우

      $this->compile();
      //
      $result = $this->_db->query($this->_compiled_query);
      if (!$result) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      if ($result && $this->_db->error() && $this->_db->error()->code != 0) {
        throw new \Exception($this->_db->error()['message'], $this->_db->error()['code']);
      }
      $list = $result->result_array();
      $field = null;
      if ($type_cast) {
        $field = $result->field_data();
      }
      foreach ($list as $data) {
        if ($type_cast) {
          $this->data_value_caster($data, $field);
        }
        $rtn_val->add(AZData::parse($data));
      }
      //
      unset($field);
      unset($type_cast);
      $this->free_results($result);
      $result->free_result();
    }

    //
    if (
        $this->is_stored_procedure() &&
        !is_null($this->get_return_parameters()) &&
        $this->get_return_parameters()->size() > 0
      ) {
      //
      $query = 'SELECT';
      //
      $idx = 0;
      $keys = $this->get_return_parameters()->keys();
      foreach ($keys as $key) {
        $query .= ($idx > 0 ? ', ' : ' ').$key.' AS '.str_replace('@', 'o_', $key);
        $idx++;
      }
      $result = $this->get_mysqli()->query($query);
      if ($result && $result->num_rows > 0) {
        //
        $fields = array();
        while ($field = $result->fetch_field()) {
          $fields[$field->name] = $field->type;
        }
        //
        $result->data_seek(0);
        //
        $data = $result->fetch_array(MYSQLI_ASSOC);
        $result->free_result();
        //
        foreach ($keys as $key) {
          $key_mod = str_replace('@', 'o_', $key);
          $value = $data[$key_mod];
          $this->value_caster($value, $fields[$key_mod]);
          $this->update_return_parameter($key, $value);
        }
        //
        $data = null;
      }
    }
    //
    return $rtn_val;
  }
  
  protected function get_multi_with_query(string $query, $type_cast = false): array {
    //
    $this->set_query($query);
    $this->clear_parameters();
    //
    return $this->get_multi($type_cast);
  }

  protected function get_multi_with_params(string $query, $params, $type_cast = false): array {
    //
    $this->set_query($query);
    $this->set_parameters($params);
    //
    return $this->get_multi($type_cast);
  }

  private function free_results(&$results) {
    while(mysqli_more_results($results->conn_id) && mysqli_next_result($results->conn_id)) {
      if($l_result = mysqli_store_result($results->conn_id)) {
        mysqli_free_result($l_result);
      }
    }
  }
}
}

namespace AZLib\AZSql {
  use \AZLib\AZData;
  use \AZLib\AZSql;

  class VALUETYPE {
    const VALUE = 1;
    const QUERY = 2;
  }

  class WHERETYPE {
    const GREATER_THAN = 'GREATER_THAN';
    const GT = 'GT';
    const GREATER_THAN_OR_EQUAL = 'GREATER_THAN_OR_EQUAL';
    const GTE = 'GTE';
    const LESS_THAN = 'LESS_THAN';
    const LT = 'LT';
    const LESS_THAN_OR_EQUAL = 'LESS_THAN_OR_EQUAL';
    const LTE = 'LTE';
    const EQUAL = 'EQUAL';
    const EQ = 'EQ';
    const NOT_EQUAL = 'NOT_EQUAL';
    const NE = 'NE';
    const BETWEEN = 'BETWEEN';
    const IN = 'IN';
    const NOT_IN = 'NOT_IN';
    const NIN = 'NIN';
    const LIKE = 'LIKE';
  }

  class CREATE_QUERY_TYPE {
    const INSERT = 1;
    const UPDATE = 2;
    const DELETE = 3;
    const SELECT = 4;
  }

  class SetData {
    public $_column;
    public $_value;
    public $_VALUETYPE;
  }

  class WhereData {
    public $_column;
    public $_value;
    public $_WHERETYPE;
    public $_VALUETYPE;
  }

  class BQuery {
    private $_table_name;
    private $_set_datas; // array
    private $_where_datas; // array
    private $_is_prepared = false;
    public function __construct(string $table_name) {
      $this->_table_name = $table_name;
    }

    public static function create(string $table_name) {
      return new BQuery($table_name);
    }
    
    public function __call($name, $args) {
      switch ($name) {
        case 'set':
          if (count($args) == 2) {
            array_push($args, null);
          }
          return call_user_func_array(array($this, 'set'), $args);
        case 'where':
          if (count($args) == 2) {
            array_push($args, null, null);
          }
          else if (count($args) == 3) {
            switch (gettype($args[2])) {
              case 'string':
                array_push($args, null);
                break;
              default:
                $args[3] = $args[2];
                $args[2] = null;
                break;
            }
          }
          return call_user_func_array(array($this, 'where'), $args);
      }
    }

    public function set_prepared($state) {
      $this->_is_prepared = $state;
      return $this;
    }

    public function is_prepared() {
      return $this->_is_prepared;
    }

    public function clear_set() {
      $this->_set_datas = null;
      return $this;
    }

    public function clear_where() {
      $this->_where_datas = null;
      return $this;
    }

    public function clear() {
      $this->clear_set();
      $this->clear_where();
      $this->set_prepared(false);
      return $this;
    }

    protected function set(string $column, $value, $VALUETYPE = null) {
      if (is_null($VALUETYPE)) $VALUETYPE = VALUETYPE::VALUE;
      //
      $set = new SetData();
      $set->_column = $column;
      $set->_value = $value;
      $set->_VALUETYPE = $VALUETYPE;
      //
      if (is_null($this->_set_datas)) $this->_set_datas = array();
      array_push($this->_set_datas, $set);
      //
      return $this;
    }

    protected function where(string $column, $value, $WHERETYPE = null, $VALUETYPE = null) {
      if (is_null($WHERETYPE)) $WHERETYPE = WHERETYPE::EQUAL;
      if (is_null($VALUETYPE)) $VALUETYPE = VALUETYPE::VALUE;
      //
      $set = new WhereData();
      $set->_column = $column;
      $set->_value = $value;
      $set->_WHERETYPE = $WHERETYPE;
      $set->_VALUETYPE = $VALUETYPE;
      //
      if (is_null($this->_where_datas)) $this->_where_datas = array();
      array_push($this->_where_datas, $set);
      //
      return $this;
    }

    public function compile($CREATE_QUERY_TYPE) {
      $query = null;
      $params = null;
      //
      switch ($CREATE_QUERY_TYPE) {
        case CREATE_QUERY_TYPE::INSERT:
          $col_str = '';
          $val_str = '';
          //
          $idx = 0;
          foreach ($this->_set_datas as $set) {
            $col_str .= "\n".($idx > 0 ? ' ,' : ' ').$set->_column;
            //
            if ($this->is_prepared() && $set->_VALUETYPE == VALUETYPE::VALUE) {
              // prepared statement 사용시
              if (is_null($params)) $params = AZData::create();
              //
              $key = "@__set_{$idx}_{$set->_column}";
              $params->add($key, $set->_value);
              $val_str .= "\n".($idx > 0 ? ' ,' : ' ').$key;
            }
            else {
              // prepared statement 미사용시
              $q_mark = "";
              if ($set->_VALUETYPE == VALUETYPE::VALUE) {
                $q_mark = $this->get_qmark($set->_value);
              }
              $val_str .= "\n".($idx > 0 ? ' ,' : ' ')."$q_mark{$set->_value}$q_mark";
            }
            $idx++;
          }
          $query = "INSERT INTO $this->_table_name ($col_str\n)\nVALUES ($val_str\n)";
          return array('query' => $query, 'parameters' => $params);
        case CREATE_QUERY_TYPE::UPDATE:
          $query = "UPDATE $this->_table_name";
          //
          if (!is_null($this->_set_datas)) {
            $query .= "\nSET";
            $idx = 0;
            foreach ($this->_set_datas as $set) {
              if ($this->is_prepared() && $set->_VALUETYPE == VALUETYPE::VALUE) {
                // prepared statement 사용시
                if (is_null($params)) $params = AZData::create();
                //
                $key = "@__set_{$idx}_{$set->_column}";
                $params->add($key, $set->_value);
                $query .= ($idx > 0 ? ',' : '')."\n {$set->_column} = $key";
              }
              else {
                // prepared statement 미사용시
                $q_mark = "";
                if ($set->_VALUETYPE == VALUETYPE::VALUE) {
                  $q_mark = $this->get_qmark($set->_value);
                }
                $query .= ($idx > 0 ? ',' : '')."\n {$set->_column} = $q_mark{$set->_value}$q_mark";
              }
              $idx++;
            }
          }
        case CREATE_QUERY_TYPE::DELETE:
          if (is_null($query)) $query = $query = "DELETE FROM $this->_table_name";
          //
          if (!is_null($this->_where_datas)) {
            $query .= "\nWHERE";
            $idx = 0;
            foreach ($this->_where_datas as $where) {
              $val_cnt = 1;
              $condition = '=';
              switch ($where->_WHERETYPE) {
                case WHERETYPE::GREATER_THAN:
                case WHERETYPE::GT:
                  $condition = '>';
                  break;
                case WHERETYPE::GREATER_THAN_OR_EQUAL:
                case WHERETYPE::GTE:
                  $condition = '>=';
                  break;
                case WHERETYPE::LESS_THAN:
                case WHERETYPE::LT:
                  $condition = '<';
                  break;
                case WHERETYPE::LESS_THAN_OR_EQUAL:
                case WHERETYPE::LTE:
                  $condition = '<=';
                  break;
                case WHERETYPE::EQUAL:
                case WHERETYPE::EQ:
                  $condition = '=';
                  break;
                case WHERETYPE::NOT_EQUAL:
                case WHERETYPE::NE:
                  $condition = '<>';
                  break;
                case WHERETYPE::BETWEEN:
                  $condition = 'BETWEEN';
                  $val_cnt = 2;
                  break;
                case WHERETYPE::IN:
                  $condition = 'IN';
                  $val_cnt = 3;
                  break;
                case WHERETYPE::NOT_IN:
                case WHERETYPE::NIN:
                  $condition = 'NOT IN';
                  $val_cnt = 3;
                  break;
                case WHERETYPE::LIKE:
                  $condition = 'LIKE';
                  break;
              }
              $query .= "\n".($idx > 0 ? ' AND ' : ' ')."{$where->_column} $condition ";
              if ($this->is_prepared() && $where->_VALUETYPE == VALUETYPE::VALUE) {
                // prepared statement 사용시
                if (is_null($params)) $params = AZData::create();
                //
                switch ($val_cnt) {
                  case 1:
                    $key = "@__where_{$idx}_{$where->_column}";
                    $params->add($key, $where->_value);
                    $query .= $key;
                    break;
                  case 2:
                    $keys = array(
                      "@__where_{$idx}_{$where->_column}_btw_1",
                      "@__where_{$idx}_{$where->_column}_btw_2"
                    );
                    $params
                      ->add($keys[0], $where->_value[0])
                      ->add($keys[1], $where->_value[1]);
                    $query .= "$keys[0] AND $keys[1]";
                    break;
                  case 3:
                    $query .= '(';
                    $jdx = 0;
                    foreach ($where->_value as $val) {
                      //
                      $key = "@__where_{$idx}_{$where->_column}_in_$jdx";
                      $params->add($key, $val);
                      //
                      $query .= ($jdx > 0 ? ',' : '').$key;
                      $jdx++;
                    }
                    $query .= ')';
                    break;
                }
              }
              else {
                // prepared statement 미사용시
                $q_mark = '';
                if ($where->_VALUETYPE == VALUETYPE::VALUE) {
                  $q_mark = $this->get_qmark($where->_value);
                }
                switch ($val_cnt) {
                  case 1:
                    $query .= "$q_mark{$where->_value}$q_mark";
                    break;
                  case 2:
                    $jdx = 0;
                    foreach ($where->_value as $val) {
                      //
                      $q_mark = $this->get_qmark($val);
                      //
                      $query .= ($jdx > 0 ? ' AND ' : '')."$q_mark{$val}$q_mark";
                      $jdx++;
                    }
                    // $query .= "$q_mark$where->_value[0]$q_mark AND $q_mark$where->_value[1]$q_mark";
                    break;
                  case 3:
                    $query .= '(';
                    $jdx = 0;
                    foreach ($where->_value as $val) {
                      //
                      $q_mark = $this->get_qmark($val);
                      //
                      $query .= ($jdx > 0 ? ',' : '')."$q_mark{$val}$q_mark";
                      $jdx++;
                    }
                    $query .= ')';
                    break;
                }
              }
              $idx++;
            }
          }
          return array('query' => $query, 'parameters' => $params);
      }
    }

    private function get_qmark($val) {
      $rtn_val = '';
      switch (gettype($val)) {
        case 'integer':
        case 'float':
        case 'double':
          $rtn_val = '';
          break;
        default:
          $rtn_val = "'";
          break;
      }
      return $rtn_val;
    }
  }

  class Basic {
    private $bquery;
    private $_db;
    //
    public function __construct(string $table_name, &$db = null) {
      //
      if (!is_null($db)) {
        $this->_db = $db;
      }
      //
      $this->bquery = new BQuery($table_name);
    }

    public static function create(string $table_name, &$db = null) {
      return new Basic($table_name, $db);
    }

    public function __call($name, $args) {
      switch ($name) {
        case 'set':
          if (count($args) == 2) {
            array_push($args, null);
          }
          return call_user_func_array(array($this, 'set'), $args);
        case 'where':
          if (count($args) == 2) {
            array_push($args, null, null);
          }
          else if (count($args) == 3) {
            switch (gettype($args[2])) {
              case 'string':
                array_push($args, null);
                break;
              default:
                $args[3] = $args[2];
                $args[2] = null;
                break;
            }
          }
          return call_user_func_array(array($this, 'where'), $args);
        case 'do_insert':
          if (count($args) == 0) {
            array_push($args, false);
          }
          return call_user_func_array(array($this, 'do_insert'), $args);
      }
    }

    public function set_prepared($state) {
      $this->bquery->set_prepared($state);
      return $this;
    }

    public function is_prepared() {
      return $this->bquery->is_prepared();
    }

    public function clear_set() {
      $this->bquery->clear_set();
      return $this;
    }

    public function clear_where() {
      $this->bquery->clear_where();
      return $this;
    }

    public function clear() {
      $this->bquery->clear();
      return $this;
    }

    protected function set(string $column, $value, $VALUETYPE = null) {
      $this->bquery->set($column, $value, $VALUETYPE);
      return $this;
    }

    protected function where(string $column, $value, $WHERETYPE = null, $VALUETYPE = null) {
      $this->bquery->where($column, $value, $VALUETYPE);
      return $this;
    }

    public function do_insert($identity = false) {
      // $time = microtime();
      $compiled = $this->bquery->compile(CREATE_QUERY_TYPE::INSERT);
      //
      $sql = AZSql::create($this->_db)
        ->set_prepared($this->is_prepared())
        ->set_query($compiled['query']);
      if ($this->is_prepared()) {
        $sql->set_parameters($compiled['parameters']);
      }
      // $this->_db['elapsed'] = microtime() - $time;
      return $sql->execute($identity);
    }

    public function do_update() {
      $compiled = $this->bquery->compile(CREATE_QUERY_TYPE::UPDATE);
      //
      $sql = AZSql::create($this->_db)
        ->set_prepared($this->is_prepared())
        ->set_query($compiled['query']);
      if ($this->is_prepared()) {
        $sql->set_parameters($compiled['parameters']);
      }
      return $sql->execute();
    }

    public function do_delete() {
      $compiled = $this->bquery->compile(CREATE_QUERY_TYPE::DELETE);
      //
      $sql = AZSql::create($this->_db)
        ->set_prepared($this->is_prepared())
        ->set_query($compiled['query']);
      if ($this->is_prepared()) {
        $sql->set_parameters($compiled['parameters']);
      }
      return $sql->execute();
    }
  }
}
