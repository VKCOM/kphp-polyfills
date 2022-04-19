<?php

namespace KPHP\InstanceSerialization;

class DeepForceFloat32 {
  /**@var any*/
  public $value = null;

  /**@param any $value*/
  public function __construct($value) {
    $this->value = $value;
  }
}
