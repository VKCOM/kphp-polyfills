<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocParsing;

use KPHP\JsonSerialization\KphpJsonDecodeException;
use RuntimeException;

class ArrayType extends PhpDocType {
  public PhpDocType $inner;

  public function __construct(PhpDocType $inner) {
    $this->inner = $inner;
  }

  protected static function parseImpl(string &$str, UseResolver $use_resolver): ?PhpDocType {
    if (parent::removeIfStartsWith($str, '(')) {
      $inside_par = PhpDocType::parse($str, $use_resolver);
      assert($inside_par && $str[0] === ')');
      $str = ltrim(substr($str, 1));
      return $inside_par;
    } else if (parent::removeIfStartsWith($str, 'array')) {
      return new ArrayType(new PrimitiveType('any'));
    } else {
      return null;
    }
  }

  public function fromUnpackedValue($value): array {
    if (!is_array($value)) {
      throw new RuntimeException('not instance: ' . $value);
    }

    if (!$this->hasInstanceInside()) {
      return $value;
    }

    $res = [];
    foreach ($value as $key => $item) {
      $res[$key] = $this->inner->fromUnpackedValue($item);
    }

    return $res;
  }

  protected function hasInstanceInside(): bool {
    return $this->inner->hasInstanceInside();
  }

  public function verifyValue($value): void {
    if (!is_array($value)) {
      throw new RuntimeException('not array: ' . $value);
    }

    foreach ($value as $item) {
      $this->inner->verifyValue($item);
    }
  }

  public function isNullAllowed(): bool {
    return false;
  }

  public function fromJson(\KPHP\JsonSerialization\JsonPath $json_path, $v, string $json_encoder) {
    if (!is_array($v) && !($v instanceof \stdClass)) {
      throw new KphpJsonDecodeException("unexpected type " . gettype($v) . " for key $json_path");
    }
    $res = [];
    $json_path->enter(null);
    foreach ($v as $k => $item) {
      $res[$k] = $this->inner->fromJson($json_path, $item, $json_encoder);
    }
    $json_path->leave();
    return $res;
  }
}

