<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocTypeParsing;

use RuntimeException;

abstract class PHPDocType {
  /*
    PHPDoc grammar

    # according to https://www.php.net/manual/en/language.oop5.basic.php
    InstanceType  ::= ^\?[A-Z][a-zA-Z0-9_\x80-\xff\]* | "self" | "static" | "object"

    #according to https://docs.phpdoc.org/latest/guides/types.html
    PrimitiveType ::= "int"     | "integer" | "float" | "string" | "array" | "mixed"
                      "boolean" | "bool"    | "false" | "true"   | "null"  | "NULL"

    TupleType     ::= "\"? "tuple(" PHPDocType ("," PHPDocType)* ")"
    ArrayType     ::= ("(" PHPDocType ")" | PHPDocType) "[]"*
    OrType        ::= PHPDocType "|" PHPDocType
    PHPDocType    ::= InstanceType   |
                      PrimitiveType  |
                      TupleType      |
                      ArrayType      |
                      OrType         |
                      "?" PHPDocType |
  */
  public static function throwRuntimeException($value, $type): void {
    $value = vk_json_encode($value);
    $type  = vk_json_encode($type);
    throw new RuntimeException("value: `${value}` doesn't correspond to type: `${type}`");
  }

  protected static function removeIfStartsWith(string &$haystack, $needle): bool {
    if (strpos($haystack, $needle) === 0) {
      $haystack = substr($haystack, strlen($needle));
      return true;
    }
    return false;
  }

  protected static function parseImpl(string &$str): ?PHPDocType {
    if (self::removeIfStartsWith($str, "?")) {
      $str = "null|({$str})";
    }

    $res = InstanceType::parse($str) ?:
           PrimitiveType::parse($str) ?:
           TupleType::parse($str) ?:
           ArrayType::parse($str);

    if (!$res) {
      return null;
    }

    $cnt_arrays = ArrayType::parseArrays($str);
    if ($cnt_arrays) {
      $res = new ArrayType($res, $cnt_arrays);
    }

    /**@var OrType */
    $or_type = OrType::parse($str);
    if ($or_type) {
      $or_type->type1 = $res;
      $res            = $or_type;
    }

    return $res;
  }

  public static function parse(string &$str): ?PHPDocType {
    $str = ltrim($str);
    $res = static::parseImpl($str);
    $str = ltrim($str);
    return $res;
  }

  /**
   * @param mixed $value
   * @return mixed
   * @throws RuntimeException
   */
  abstract public function fromUnpackedValue($value, UseResolver $use_resolver);

  /**
   * @param mixed $value
   * @throws RuntimeException
   */
  abstract public function verifyValue($value, UseResolver $use_resolver): void;

  abstract protected function hasInstanceInside(): bool;
  abstract protected function hasNullInside(): bool;
  abstract protected function getDefaultValue();

  abstract public function storeValueToMap(string $name, $value, array &$map, string $encoder_name, UseResolver $use_resolver): void;
  abstract public function decodeValue($value, string $encoder_name, UseResolver $use_resolver);
}
