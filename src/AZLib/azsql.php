<?php
namespace AZLib;
use AZLib\{AZData, AZList};
class AZSql {
  // protected $CI;
  private $_db;
  // private $_query;
  // private $_params;
  public function __construct(&$db = null) {
    // $this->CI =& get_instance();
    //
    if (!is_null($db)) {
      $this->_db = $db;
    }
    /*
    $query = 'SELECT * FROM bsg.admin_user WHERE au_key=@au_key AND au_name=@au_name';
    echo "query:{$query}\n";
    $key = '@au_key';
    $query = $this->param_replacer($query, '@au_key', 1);
    $query = $this->param_replacer($query, '@au_name', 'test_admin');
    echo "query:{$query}\n";
    */
  }

  public static function create(&$db = null) {
    return new AZSql($db);
  }

  private function param_replacer(string $query, string $key, $value) {
    $query = preg_replace("/{$key}$/", $this->_db->escape($value), $query);
    $query = preg_replace("/{$key}\\r\\n/", $this->_db->escape($value)."\\r\\n", $query);
    $query = preg_replace("/{$key}\\n/", $this->_db->escape($value)."\\n", $query);
    $query = preg_replace("/{$key}\s/", $this->_db->escape($value)." ", $query);
    $query = preg_replace("/{$key}\t/", $this->_db->escape($value)."\\t", $query);
    $query = preg_replace("/{$key},/", $this->_db->escape($value).",", $query);
    $query = preg_replace("/{$key}\)/", $this->_db->escape($value).")", $query);
    return $query;
  }

  private function value_caster(&$value, $type) {
    switch ($type) {
      case 'tinyint':
      case 'smallint':
      case 'mediumint':
      case 'integer':
      case 'bigint':
        $value = intval($value);
        break;
      case 'float':
        $value = floatval($value);
        break;
      case 'decimal':
      case 'double':
        $value = doubleval($value);
        break;
    }
  }

  private function data_value_caster(array &$data, $metadata) {
    // $keys = array_keys($data);
    foreach ($data as $key => &$value) {
      foreach ($metadata as $meta) {
        if ($meta->name === $key) {
          $this->value_caster($value, $meta->type);
          break;
          /*
          switch ($meta->type) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
              // echo "meta.".$key.".name:".$meta->name." / type:".$meta->type."\n";
              // echo "proc - ".$key.":".gettype($value);
              $value = intval($value);
              // echo " -> ".gettype($value)."\n";
              break;
            case 'float':
              $value = floatval($value);
              break;
            case 'decimal':
            case 'double':
              $value = doubleval($value);
              break;
          }
          */
        }
      }
    }
  }

  public function __call($name, $args) {
    switch ($name) {
      case 'execute':
      case 'get':
      case 'get_data':
      case 'get_list':
        switch (count($args)) {
          case 1:
            return call_user_func_array(array($this, $name), $args);
          case 2:
            if (gettype($args[1]) === 'boolean') {
              return call_user_func_array(array($this, $name), $args);
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

  protected function execute(string $query, bool $identity = false): int {
    $rtn_val = 0;
    $this->_db->simple_query($query);
    $rtn_val = $identity ? $this->_db->insert_id() : $this->_db->affected_rows();
    return $rtn_val;
  }

  protected function execute_with_params(string $query, $params, bool $identity = false): int {
    if (gettype($params) === 'object' && $params instanceof AZData) {
      $params = $params->to_json();
    }
    //
    foreach ($params as $key => $value) {
      $query = $this->param_replacer($query, $key, $value);
    }
    //
    $rtn_val = -1;
    $q_result = $this->_db->simple_query($query);
    if ($q_result) {
      $rtn_val = $identity ? $this->_db->insert_id() : $this->_db->affected_rows();
    }
    return $rtn_val;
  }

  protected function get(string $query, $type_cast = false) {
    $rtn_val = null;
    $q_result = $this->_db->query($query);
    $data = $q_result->row_array();
    if (count($data) > 0) {
      $rtn_val = array_shift($data);
      //
      if ($type_cast) {
        $field = $q_result->field_data();
        // echo "get.field:".json_encode($field)."\n";
        $this->value_caster($rtn_val, $field[0]->type);
        unset($field);
      }
    }
    //
    unset($data);
    unset($type_cast);
    //
    $q_result->free_result();
    //
    return $rtn_val;
  }

  protected function get_with_params(string $query, $params, $type_cast = false) {
    if (gettype($params) === 'object' && $params instanceof AZData) {
      $params = $params->to_json();
    }
    //
    foreach ($params as $key => $value) {
      $query = $this->param_replacer($query, $key, $value);
    }
    //
    $rtn_val = null;
    $q_result = $this->_db->query($query);
    $data = $q_result->row_array();
    if (count($data) > 0) {
      $rtn_val = array_shift($data);
      //
      if ($type_cast) {
        $field = $q_result->field_data();
        // echo "get.field:".json_encode($field)."\n";
        $this->value_caster($rtn_val, $field[0]->type);
        unset($field);
      }
    }
    //
    unset($data);
    unset($params);
    unset($type_cast);
    //
    $q_result->free_result();
    //
    return $rtn_val;
  }

  /**
   * 지정된 쿼리 문자열에 대한 단일 행 결과를 AZData 객체로 반환
   * @param string $query 쿼리 문자열
   * @param bool $type_cast = false 결과값을 DB의 자료형에 맞춰서 type casting 할지 여부
   * @return AZData
   */
  protected function get_data(string $query, $type_cast = false): AZData {
    // echo "get_data.query:".$query."\n";
    $q_result = $this->_db->query($query);
    $data = $q_result->row_array();
    // echo "\nget_data.data.PRE:".json_encode($data)."\n";
    if ($type_cast) {
      $field = $q_result->field_data();
      // echo "get_data.field:".json_encode($field)."\n";
      $this->data_value_caster($data, $field);
      unset($field);
    }
    //
    unset($type_cast);
    //
    $q_result->free_result();
    // echo "get_data.data.POST:".json_encode($data)."\n";
    // return AZData::parse($this->_db->query($query)->row_array());
    return AZData::parse($data);
  }

  /**
   * 지정된 쿼리 문자열에 대한 단일 행 결과를 AZData 객체로 반환
   * @param string $query 쿼리 문자열
   * @param AZData|array $params 쿼리 문자열에 등록된 대체 문자열 자료
   * @param bool $type_cast = false 결과값을 DB의 자료형에 맞춰서 type casting 할지 여부
   * @return AZData
   */
  protected function get_data_with_params(string $query, $params, $type_cast = false): AZData {
    // echo "with_params - params.type:".gettype($params)."\n";
    // echo "with_params - params is instanceof:".($params instanceof AZData)."\n";
    if (gettype($params) === 'object' && $params instanceof AZData) {
      $params = $params->to_json();
    }
    //
    foreach ($params as $key => $value) {
      $query = $this->param_replacer($query, $key, $value);
    }
    // echo "with_params - query:".$query."\n";
    //
    $q_result = $this->_db->query($query);
    $data = $q_result->row_array();
    if ($type_cast) {
      $field = $q_result->field_data();
      $this->data_value_caster($data, $field);
      unset($field);
    }
    //
    unset($params);
    unset($type_cast);
    //
    $q_result->free_result();
    //
    // return AZData::parse($this->_db->query($query)->row_array());
    return AZData::parse($data);
  }

  /**
   * 지정된 쿼리 문자열에 대한 다행 결과를 AZList 객체로 반환
   */
  protected function get_list(string $query, $type_cast = false): AZList {
    //
    $rtn_val = new AZList();
    //
    $q_result = $this->_db->query($query);
    $list = $q_result->result_array();
    $field = null;
    if ($type_cast) {
      $field = $q_result->field_data();
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
    //
    $q_result->free_result();
    return $rtn_val;
  }

  protected function get_list_with_params(string $query, $params, $type_cast = false) {
    //
    $rtn_val = new AZList();
    //
    if (gettype($params) === 'object' && $params instanceof AZData) {
      $params = $params->to_json();
    }
    //
    foreach ($params as $key => $value) {
      $query = $this->param_replacer($query, $key, $value);
    }
    //
    $q_result = $this->_db->query($query);
    $list = $q_result->result_array();
    $field = null;
    if ($type_cast) {
      $field = $q_result->field_data();
    }
    foreach ($list as $data) {
      if ($type_cast) {
        $this->data_value_caster($data, $field);
      }
      $rtn_val->add(AZData::parse($data));
    }
    //
    unset($field);
    unset($params);
    unset($type_cast);
    //
    $q_result->free_result();
    //
    return $rtn_val;
  }
}
