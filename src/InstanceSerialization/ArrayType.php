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
  /**@var ?PHPDocType */
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
   * @param mixed[] $arr
   * @throws RuntimeException
   */
  public function fromUnpackedValue($arr, UseResolver $use_resolver): array {
    if (!is_array($arr)) {
      throw new RuntimeException('not instance: ' . $arr);
    }

    if (!$this->hasInstanceInside()) {
      return $arr;
    }

    $res = [];
    foreach ($arr as $key => $value) {
      if ($this->cnt_arrays === 1) {
        $res[$key] = $this->inner_type->fromUnpackedValue($value, $use_resolver);
      } else {
        $this->cnt_arrays -= 1;
        $res[$key] = $this->fromUnpackedValue($value, $use_resolver);
        $this->cnt_arrays += 1;
      }
    }

    return $res;
  }

  protected function hasInstanceInside(): bool {
    return $this->inner_type->hasInstanceInside();
  }

  public function verifyValueImpl($array, UseResolver $use_resolver): void {
    if (!is_array($array)) {
      throw new RuntimeException('not instance: ' . $array);
    }

    foreach ($array as $value) {
      if ($this->cnt_arrays === 1) {
        $this->inner_type->verifyValue($value, $use_resolver);
      } else {
        $this->cnt_arrays -= 1;
        $this->verifyValueImpl($value, $use_resolver);
        $this->cnt_arrays += 1;
      }
    }
  }
}

