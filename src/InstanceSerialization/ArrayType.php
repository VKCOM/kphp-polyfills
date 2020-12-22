<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\InstanceSerialization;

use RuntimeException;

class ArrayType extends PHPDocType {
  /**@var PHPDocType|null */
  public $inner_type = null;

  /**@var int */
  public $cnt_arrays = 0;

  public function __construct(PHPDocType $inner_type, int $cnt_arrays) {
    $this->inner_type = $inner_type;
    $this->cnt_arrays = $cnt_arrays;
  }

  protected static function parseImpl(string &$str): ?PHPDocType {
    if (parent::removeIfStartsWith($str, '(')) {
      $inner_type = PHPDocType::parse($str);
      assert($inner_type && $str[0] === ')');
      $str = ltrim(substr($str, 1));
    } else {
      return null;
    }

    $cnt_arrays = self::parseArrays($str);
    if ($cnt_arrays) {
      return new ArrayType($inner_type, $cnt_arrays);
    }

    return $inner_type;
  }

  public static function parseArrays(string &$str): int {
    $cnt_arrays = 0;
    while (parent::removeIfStartsWith($str, '[]')) {
      ++$cnt_arrays;
    }

    return $cnt_arrays;
  }

  /**
   * @param array       $arr
   * @param UseResolver $use_resolver
   * @return array
   * @throws RuntimeException
   */
  public function fromUnpackedValue($arr, UseResolver $use_resolver): array {
    if (is_array($arr) && !$this->hasInstanceInside()) {
      return $arr;
    }

    $value_collector = function($value) use ($use_resolver) {
      return $this->inner_type->fromUnpackedValue($value, $use_resolver);
    };
    return $this->runOnEachValue($arr, $value_collector, $this->cnt_arrays);
  }

  protected function hasInstanceInside(): bool {
    return $this->inner_type->hasInstanceInside();
  }

  private function runOnEachValue($arr, callable $callback, int $cnt_arrays): array {
    if (!is_array($arr)) {
      throw new RuntimeException('not instance: ' . $arr);
    }

    $res = [];
    foreach ($arr as $key => $value) {
      $res[$key] = $cnt_arrays === 1
        ? $callback($value)
        : $this->runOnEachValue($value, $callback, $cnt_arrays - 1);
    }

    return $res;
  }

  public function verifyValueImpl($value, UseResolver $use_resolver): void {
    $value_verifier = function($value) use ($use_resolver) {
      $this->inner_type->verifyValue($value, $use_resolver);
      return true;
    };
    $this->runOnEachValue($value, $value_verifier, $this->cnt_arrays);
  }
}

