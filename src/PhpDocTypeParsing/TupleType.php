<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocTypeParsing;

use RuntimeException;

class TupleType extends PHPDocType {
  /**@var PHPDocType[] */
  public $types = [];

  /** @param PHPDocType[] $types */
  public function __construct(array $types) {
    $this->types = $types;
  }

  /** @throws RuntimeException */
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
   * @param mixed $value
   * @throws RuntimeException
   */
  public function fromUnpackedValue($value, UseResolver $use_resolver): array {
    $this->checkValue($value);
    $res = [];
    for ($i = 0, $i_max = count($value); $i < $i_max; $i++) {
      $res[] = $this->types[$i]->fromUnpackedValue($value[$i], $use_resolver);
    }

    return $res;
  }

  /** @param mixed $value */
  public function verifyValue($value, UseResolver $use_resolver): void {
    $this->checkValue($value);
    for ($i = 0, $i_max = count($value); $i < $i_max; $i++) {
      $this->types[$i]->verifyValue($value[$i], $use_resolver);
    }
  }

  protected function hasInstanceInside(): bool {
    return in_array(true, array_map(fn(PHPDocType $type) => $type->hasInstanceInside(), $this->types), true);
  }

  protected function hasNullInside(): bool {
    return false;
  }

  /** @param mixed $value */
  private function checkValue($value): void {
    if (!is_array($value)) {
      self::throwRuntimeException($value, $this->types);
    }

    if (count($this->types) !== count($value)) {
      self::throwRuntimeException($value, $this->types);
    }
  }

  public function encodeValue($value, string $encoder_name, UseResolver $use_resolver) {
    // TODO
  }

  protected function getDefaultValue() {
    // TODO
  }

  public function decodeValue($value, string $encoder_name, UseResolver $use_resolver) {
    // TODO
  }
}

