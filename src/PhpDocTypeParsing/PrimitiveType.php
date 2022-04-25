<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocTypeParsing;

use RuntimeException;

class PrimitiveType extends PHPDocType {
  /**@var string[] */
  public static $primitive_types = [
    'int', 'integer', 'float',
    'string', 'array',
    'boolean', 'bool', 'false', 'true',
    'null', 'NULL',
    'mixed',
  ];
  /**@var string */
  public $type = '';

  protected static function parseImpl(string &$str): ?PHPDocType {
    foreach (self::$primitive_types as $primitive_type) {
      if (self::removeIfStartsWith($str, $primitive_type)) {
        $res       = new self();
        $res->type = $primitive_type;
        return $res;
      }
    }

    return null;
  }

  /**
   * @param mixed       $value
   * @return mixed
   * @throws RuntimeException
   */
  public function fromUnpackedValue($value, UseResolver $use_resolver) {
    $true_type = $this->getPHPCompliantType();
    if ($true_type === 'mixed' || gettype($value) === $true_type) {
      return $value;
    }

    throw new RuntimeException('not primitive: ' . $this->type);
  }

  private function getPHPCompliantType(): string {
    switch ($this->type) {
      case 'int':
        return 'integer';
      case 'float':
        return 'double';
      case 'boolean':
      case 'bool':
      case 'false':
      case 'true':
        return 'boolean';
      case 'null':
        return 'NULL';
    }
    return $this->type;
  }

  public function verifyValue($value, UseResolver $_): void {
    $true_type = $this->getPHPCompliantType();
    if (gettype($value) === $true_type) {
      if (($this->type === 'true' && $value !== true) ||
          ($this->type === 'false' && $value !== false)) {
        self::throwRuntimeException($value, $this->type);
      }
      return;
    }

    if (is_object($value) || $true_type !== 'mixed') {
      self::throwRuntimeException($value, $this->type);
    }

    if (!is_array($value)) {
      return;
    }

    $check_objects_inside = function($item, $_) {
      if (is_object($item)) {
        self::throwRuntimeException($item, $this->type);
      }
    };
    array_walk_recursive($value, $check_objects_inside);
  }

  protected function hasInstanceInside(): bool {
    return false;
  }

  protected function hasNullInside(): bool {
    return $this->type === "null";
  }

  protected function getDefaultValue() {
    settype($value, $this->type !== "mixed" ? $this->type : "null");
    return $value;
  }

  public function encodeValue($value, string $_, UseResolver $use_resolver, int $float_precision, bool $array_as_hashmap = false) {
    if ($value === null) {
      $value = $this->getDefaultValue();
    }
    $this->verifyValue($value, $use_resolver);
    if ($this->type === 'float' && (is_nan($value) || is_infinite($value))) {
      // json_encode() can't deal with nan or infs
      return 0.0;
    }
    if ($this->type === 'float' && $float_precision) {
      // just truncate $value
      return (float)bcadd($value, 0, $float_precision);
    }
    // wtf why primitive type can be array?
    if ($this->type === 'array' && $array_as_hashmap) {
      return (object)$value;
    }
    return $value;
  }

  public function decodeValue($value, string $_, UseResolver $use_resolver) {
    if ($value === null) {
      $value = $this->getDefaultValue();
    }
    return $this->fromUnpackedValue($value, $use_resolver);
  }
}
