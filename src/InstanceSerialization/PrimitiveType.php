<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\InstanceSerialization;

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
   * @param UseResolver $use_resolver
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
}
