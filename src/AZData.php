<?php
/**
 * @file
 */
namespace MParang\AZLib;
use MParang\AZLib\{AZList};

class AZData implements \Iterator, \JsonSerializable, \ArrayAccess {
  // protected $CI;
  private $_key = 0;
  private $_keys = null;
  private $_data = null;
  public function __construct($json = null) {
    // $this->CI =& get_instance();
    //
    if (!is_null($json)) {
      $type = gettype($json);
      switch ($type) {
        case 'string':
          $this->_data = json_decode($json, true);
          break;
        case 'array':
          $this->_data = $json;
          break;
      }
      //
      switch ($type) {
        case 'string':
        case 'array':
          $this->_keys = array_keys($this->_data);
          break;
      }
    }
  }

  /**
   * 객체 생성 후 반환
   * @return AZData
   */
  public static function create(): AZData {
    return new AZData();
  }

  /**
   * string 형식의 json 문자열 또는 array 객체를 기반으로 자료 생성
   * @param string|array $json = null json 문자열 또는 array 객체
   * @return AZData
   */
  public static function parse($json = null): AZData {
    return new AZData($json);
  }

  /**
   * get, remove method에 대한 오버로딩 처리
   */
  public function __call($name, $args) {
    switch ($name) {
      case 'get':
        switch (gettype($args[0])) {
          case 'tinyint':
          case 'smallint':
          case 'mediumint':
          case 'integer':
          case 'bigint':
            return call_user_func_array(array($this, 'get_by_index'), $args);
          case 'string':
            return call_user_func_array(array($this, "get_by_key"), $args);
        }
        // no break
        case 'remove':
          switch (gettype($args[0])) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'integer':
            case 'bigint':
              return call_user_func_array(array($this, 'remove_by_index'), $args);
            case 'string':
              return call_user_func_array(array($this, "remove_by_key"), $args);
          }
      break;
    }
  }

  // ArrayAccess 구현용
  public function offsetExists($offset) {
    switch (gettype($offset)) {
      case 'tinyint':
      case 'smallint':
      case 'mediumint':
      case 'integer':
      case 'bigint':
        return $offset > -1 && $offset < count($this->_data);
    }
    return $this->has_key($offset);
  }

  // ArrayAccess 구현용
  public function offsetGet($offset) {
    return $this->get($offset);
  }

  // ArrayAccess 구현용
  public function offsetSet($offset, $value) {
    $this->set($offset, $value);
  }

  // ArrayAccess 구현용
  public function offsetUnset($offset) {
    $this->remove($offset);
  }
    
  // iterator 구현용
  public function current() {
    return $this->get($this->_key);
  }

  // iterator 구현용
  public function key() {
    return $this->_key;
  }

  // iterator 구현용
  public function next() {
    ++$this->_key;
  }

  // iterator 구현용
  public function rewind() {
    $this->_key = 0;
  }

  // iterator 구현용
  public function valid() {
    return isset($this->_keys[$this->_key]);
  }

  /**
   * 지정된 키값이 정의되어 있는지 여부 반환
   * @param string $key 정의 여부 확인을 위한 키값
   * @return bool
   */
  public function has_key(string $key): bool {
    return array_key_exists($key, $this->_data) > 0;
    // return isset($this->_data[$key]);
  }

  /**
   * 현재 가지고 있는 전체 키 목록을 배열로 반환
   * @return array
   */
  public function keys() {
    return array_keys($this->_data);
  }

  /**
   * 지정된 index에 위치한 자료의 key 반환
   * @return string
   */
  public function get_key($idx): string {
    return array_keys($this->_data)[$idx];
  }

  /**
   * index값 기준 자료 반환
   * @param $idx index값
   * @return mixed
   */
  protected function get_by_index($idx) {
    if (is_null($this->_data)) return null;
    $cnt = count($this->_data);
    if ($idx < 0 || $idx >= $cnt) {
      return null;
    }
    return $this->_data[$this->_keys[$idx]];
  }

  /**
   * key값 기준 자료 반환
   * @param string $key key값
   * @return mixed
   */
  protected function get_by_key(string $key) {
    if (!$this->has_key($key)) {
      return null;
    }
    return $this->_data[$key];
  }

  public function size() {
    return count($this->_keys);
  }

  /**
   * 자료 추가
   * @param string $key 키값
   * @param mixed $value
   * @return AZData
   */
  public function add(string $key, $value) {
    if (is_null($this->_data)) {
      $this->_data = array();
      $this->_keys = array();
    }
    $this->_data[$key] = $value;
    array_push($this->_keys, $key);
    return $this;
  }

  public function set(string $key, $value) {
    if (!is_null($this->_data)) {
      if (!$this->has_key($key)) {
        $this->add($key, $value);
      }
      else {
        $this->_data[$key] = $value;
      }
    }
    return $this;
  }

  protected function remove_by_key(string $key) {
    $this->_data[$key] = null;
    unset($this->_data[$key]);
    return $this;
  }

  protected function remove_by_index($idx) {
    if ($idx > -1 && $idx < count($this->_data)) {
      $key = $this->_keys[$idx];
      array_splice($this->_keys, $idx, 1);
      reset($this->_keys);
      //
      $this->_data[$key] = null;
      unset($this->_data[$key]);
    }
    return $this;
  }

  public function convert($model) {
    if (gettype($model) === 'string') {
      $reflection = new ReflectionClass($model);
      $model = $reflection->newInstance();
    }
    $reflection = new ReflectionClass($model);
    $properties = $reflection->getProperties();
    foreach ($properties as $property) {
      $name = $property->getName();
      if ($this->has_key($name) && !$property->isStatic()) {
        $property->setAccessible(true);
        $property->setValue($model, $this->get($name));
        $property->setAccessible(false);
      }
    }
    return $model;
  }

  public function to_json() {
    $rtn_val = $this->_data;
    if (is_null($this->_data)) {
      $rtn_val = json_decode('{}');
    }
    else {
      $keys = array_keys($rtn_val);
      for ($i = 0; $i < count($keys); $i++) {
        if ($rtn_val[$keys[$i]] instanceof AZData) {
          $rtn_val[$keys[$i]] = $rtn_val[$keys[$i]]->to_json();
        } elseif ($rtn_val[$keys[$i]] instanceof AZList) {
          $rtn_val[$keys[$i]] = $rtn_val[$keys[$i]]->to_json();
        }
      }
    }
    return $rtn_val;
  }

  public function to_json_string(): string {
    return json_encode($this->to_json());
  }

  public function jsonSerialize(): string {
    return $this->to_json_string();
  }
}
