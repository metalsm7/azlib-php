<?php
/**
 * @file
 */
namespace AZLib;
use AZLib\{AZData};
class AZList implements \Iterator, \JsonSerializable {
  // protected $CI;
  private $_key = 0;
  private $_data = array();
  public function __construct(string $json = null) {
    // $this->CI =& get_instance();
    //
    if (!is_null($json)) {
      $this->_data = json_decode($json, true);
    }
  }

  public static function create(): AZList {
    return new AZList();
  }

  public static function parse(string $json = null): AZList {
    return new AZList($json);
  }

  // iterator 구현용
  public function current() {
    return $this->_data[$this->_key];
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
    return isset($this->_data[$this->_key]);
  }

  public function size() {
    if (is_null($this->_data)) {
      return 0;
    }
    return count($this->_data);
  }

  public function get(int $idx) {
    if ($this->size() >= $idx + 1) {
      return $this->_data[$idx];
    }
    return null;
  }

  public function add(AZData $data) {
    array_push($this->_data, $data);
    return $this;
  }

  public function remove(int $idx) {
    if ($this->size() >= $idx + 1) {
      unset($this->_data[$idx]);
    }
    return $this;
  }

  public function push(AZData $data) {
    array_push($this->_data, $data);
    return $this;
  }

  public function pop() {
    return array_pop($this->_data);
  }

  public function shift() {
    return array_shift($this->_data);
  }

  public function unshift(AZData $data) {
    array_unshift($this->_data, $data);
    return $this;
  }

  public function convert($model): array {
    $rtn_val = array();
    foreach ($this->_data as $data) {
      array_push($rtn_val, $data->convert($model));
    }
    return $rtn_val;
  }

  public function to_json() {
    $rtn_val = $this->_data;
    if (!is_null($rtn_val)) {
      for ($i = 0; $i < count($rtn_val); $i++) {
        if ($rtn_val[$i] instanceof AZData) {
          $rtn_val[$i] = $rtn_val[$i]->to_json();
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
