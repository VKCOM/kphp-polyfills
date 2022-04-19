<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocTypeParsing;

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
  private function traverseArray($arr, string $on_array_member, UseResolver $use_resolver): array {
    if (!is_array($arr)) {
      throw new RuntimeException('not instance: ' . $arr);
    }

    if (!$this->hasInstanceInside()) {
      return $arr;
    }

    $res = [];
    foreach ($arr as $key => $value) {
      if ($this->cnt_arrays === 1) {
        $res[$key] = $this->inner_type->$on_array_member($value, $use_resolver);
      } else {
        $this->cnt_arrays -= 1;
        $res[$key] = $this->$on_array_member($value, $use_resolver);
        $this->cnt_arrays += 1;
      }
    }

    return $res;
  }

  /**
   * @param mixed[] $arr
   * @throws RuntimeException
   */
  public function fromUnpackedValue($arr, UseResolver $use_resolver): array {
    return $this->traverseArray($arr, "fromUnpackedValue", $use_resolver);
  }

  protected function hasInstanceInside(): bool {
    return $this->inner_type->hasInstanceInside();
  }

  public function verifyValue($array, UseResolver $use_resolver): void {
    if (!is_array($array)) {
      throw new RuntimeException('not instance: ' . $array);
    }

    foreach ($array as $value) {
      if ($this->cnt_arrays === 1) {
        $this->inner_type->verifyValue($value, $use_resolver);
      } else {
        $this->cnt_arrays -= 1;
        $this->verifyValue($value, $use_resolver);
        $this->cnt_arrays += 1;
      }
    }
  }

  public function storeValueToMap(string $name, $value, array &$map, UseResolver $use_resolver): void {
    if (!is_array($value)) {
      throw new RuntimeException('not array: ' . $value);
    }

    $map_arr = [];
    foreach ($value as $k => $v) {
      if ($this->cnt_arrays === 1) {
        $this->inner_type->storeValueToMap($k, $v, $map_arr, $use_resolver);
      } else {
        $this->cnt_arrays -= 1;
        $this->storeValueToMap($k, $v, $map_arr, $use_resolver);
        $this->cnt_arrays += 1;
      }
    }
    $map[$name] = $map_arr;
  }

  public function decodeValue($arr, UseResolver $use_resolver): array {
    return $this->traverseArray($arr, "decodeValue", $use_resolver);
  }
}

