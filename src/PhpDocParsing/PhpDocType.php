<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocParsing;

use RuntimeException;

abstract class PhpDocType {
  public static function throwRuntimeException($value, $type): void {
    if (function_exists('vk_json_encode')) {
      $value_str = vk_json_encode($value);
      $type_str = vk_json_encode($type);
    } else {
      $value_str = json_encode($value);
      $type_str = json_encode($type);
    }
    throw new RuntimeException("can't assign to type $type_str from $value_str");
  }

  protected static function removeIfStartsWith(string &$haystack, $needle): bool {
    if (strpos($haystack, $needle) === 0) {
      $haystack = substr($haystack, strlen($needle));
      return true;
    }
    return false;
  }

  protected static function parseImpl(string &$str, UseResolver $use_resolver): ?PhpDocType {
    $nullable = self::removeIfStartsWith($str, "?");

    $res = InstanceType::parse($str, $use_resolver) ?:
           PrimitiveType::parse($str, $use_resolver) ?:
           TupleType::parse($str, $use_resolver) ?:
           ArrayType::parse($str, $use_resolver);

    if (!$res) {
      return null;
    }

    while (self::removeIfStartsWith($str, '[]')) {
      $res = new ArrayType($res);
    }

    /**@var OrType */
    $or_type = OrType::parse($str, $use_resolver);
    if ($or_type) {
      $or_type->type1 = $res;
      $res            = $or_type;
    }

    if ($nullable) {
      $or_type = new OrType();
      $or_type->type1 = new PrimitiveType('null');
      $or_type->type2 = $res;
      $res = $or_type;
    }

    return $res;
  }

  public static function parse(string &$str, UseResolver $use_resolver): ?PhpDocType {
    $str = ltrim($str);
    $res = static::parseImpl($str, $use_resolver);
    $str = ltrim($str);
    return $res;
  }

  /**
   * @param mixed $value
   * @return mixed
   * @throws RuntimeException
   */
  abstract public function fromUnpackedValue($value);

  /**
   * @param mixed $value
   * @throws RuntimeException
   */
  abstract public function verifyValue($value): void;

  abstract protected function hasInstanceInside(): bool;

  abstract public function isNullAllowed(): bool;

  /**
   * @param mixed|object $v
   * @return mixed|object
   */
  abstract public function fromJson(\KPHP\JsonSerialization\JsonPath $json_path, $v, string $json_encoder);
}
