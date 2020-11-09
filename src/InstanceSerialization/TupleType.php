<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\InstanceSerialization;

use RuntimeException;

class TupleType extends PHPDocType {
  /**@var PHPDocType[] */
  public $types = [];

  /** @param PHPDocType[] $types */
  public function __construct(array $types) {
    $this->types = $types;
  }

  /**
   * @param string $str
   * @return PHPDocType|null
   * @throws RuntimeException
   */
  protected static function parseImpl(string &$str): ?PHPDocType {
    if (!parent::removeIfStartsWith($str, '\\tuple(') && !parent::removeIfStartsWith($str, 'tuple(')) {
      return null;
    }

    $types = [];
    while (true) {
      $cur_type = PHPDocType::parse($str);
      if (!$cur_type) {
        throw new RuntimeException('something went wrong in parsing tuple phpdoc');
      }

      $types[] = $cur_type;
      $str     = ltrim($str);
      if ($str[0] === ',') {
        $str = substr($str, 1);
      } elseif ($str[0] === ')') {
        $str = substr($str, 1);
        break;
      } else {
        throw new RuntimeException('phpdoc parsing error `,` or `)` expected');
      }
    }

    return new TupleType($types);
  }

  /**
   * @param mixed       $value
   * @param UseResolver $use_resolver
   * @return array
   * @throws RuntimeException
   */
  public function fromUnpackedValue($value, UseResolver $use_resolver): array {
    $res             = [];
    $value_collector = static function(PHPDocType $type, $value) use ($use_resolver, &$res) {
      $res[] = $type->fromUnpackedValue($value, $use_resolver);
    };
    $this->runOnEachValue($value, $value_collector);

    return $res;
  }

  private function runOnEachValue($value, callable $callback): void {
    if (!is_array($value)) {
      self::throwRuntimeException($value, $this->types);
    }

    if (count($this->types) !== count($value)) {
      self::throwRuntimeException($value, $this->types);
    }

    for ($i = 0, $i_max = count($value); $i < $i_max; $i++) {
      $callback($this->types[$i], $value[$i]);
    }
  }

  public function verifyValue($value, UseResolver $use_resolver): void {
    $value_verifier = static function(PHPDocType $type, $value) use ($use_resolver) {
      $type->verifyValue($value, $use_resolver);
    };
    $this->runOnEachValue($value, $value_verifier);
  }

  protected function hasInstanceInside(): bool {
    return in_array(true, array_map([$this, 'hasInstanceInside'], $this->types), true);
  }
}

