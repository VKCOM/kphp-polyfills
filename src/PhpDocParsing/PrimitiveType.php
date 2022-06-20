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

class PrimitiveType extends PhpDocType {
  private const PRIMITIVE_TYPES = [
    'int', 'integer', 'float', 'string',
    'boolean', 'bool', 'false', 'true',
    'null', 'NULL',
    'mixed', 'any',
  ];

  public string $ptype;

  function __construct(string $ptype) {
    $this->ptype = $ptype;
  }

  protected static function parseImpl(string &$str, UseResolver $use_resolver): ?PhpDocType {
    foreach (self::PRIMITIVE_TYPES as $primitive_type) {
      if (self::removeIfStartsWith($str, $primitive_type)) {
        return new self($primitive_type);
      }
    }

    return null;
  }

  public function fromUnpackedValue($value) {
    if ($this->doesValueFitThisType($value)) {
      return $value;
    }

    throw new RuntimeException('not primitive: ' . $this->ptype);
  }

  private function doesValueFitThisType($v): bool {
    switch ($this->ptype) {
      case 'int':
      case 'integer':
        return is_int($v);
      case 'float':
        return is_int($v) || is_double($v);
      case 'string':
        return is_string($v);
      case 'bool':
      case 'boolean':
        return $v === true || $v === false;
      case 'false':
        return $v === false;
      case 'true':
        return $v === true;
      case 'null':
      case 'NULL':
        return $v === null;
      case 'mixed':
        // for an array, do a very primitive check that it's an array of mixed-compatible values
        if (is_array($v) && count($v) > 0) {
          return $this->doesValueFitThisType(array_first_value($v)) && $this->doesValueFitThisType(array_last_value($v));
        }
        if ($v instanceof \stdClass) {
          return true;
        }
        return !is_object($v);
      case 'any':
        return true;
      default:
        return false;
    }
  }

  public function verifyValue($value): void {
    if (!$this->doesValueFitThisType($value)) {
      self::throwRuntimeException($value, $this->ptype);
    }
  }

  protected function hasInstanceInside(): bool {
    return false;
  }

  public function isNullAllowed(): bool {
    return $this->ptype === 'null' || $this->ptype === 'NULL' || $this->ptype === 'mixed' || $this->ptype === 'any';
  }

  public function fromJson(\KPHP\JsonSerialization\JsonPath $json_path, $v, string $json_encoder) {
    if (!$this->doesValueFitThisType($v)) {
      throw new KphpJsonDecodeException("unexpected type " . gettype($v) . " for key $json_path");
    }

    return $v;
  }
}
