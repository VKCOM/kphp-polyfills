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

class TupleType extends PhpDocType {
  /** @var PhpDocType[] */
  public array $types = [];

  public function __construct(array $types) {
    $this->types = $types;
  }

  protected static function parseImpl(string &$str, UseResolver $use_resolver): ?PhpDocType {
    if (!parent::removeIfStartsWith($str, '\\tuple(') && !parent::removeIfStartsWith($str, 'tuple(')) {
      return null;
    }

    $types = [];
    while (true) {
      $cur_type = PhpDocType::parse($str, $use_resolver);
      if (!$cur_type) {
        throw new RuntimeException('something went wrong in parsing tuple phpdoc');
      }

      $types[] = $cur_type;
      $str     = ltrim($str);
      $chr     = $str === '' ? '' : $str[0];
      if ($chr === ',') {
        $str = substr($str, 1);
      } elseif ($chr === ')') {
        $str = substr($str, 1);
        break;
      } else {
        throw new RuntimeException('phpdoc parsing error `,` or `)` expected');
      }
    }

    return new TupleType($types);
  }

  public function fromUnpackedValue($value): array {
    $this->checkValue($value);
    $res = [];
    for ($i = 0, $i_max = count($value); $i < $i_max; $i++) {
      $res[] = $this->types[$i]->fromUnpackedValue($value[$i]);
    }

    return $res;
  }

  public function verifyValue($value): void {
    $this->checkValue($value);
    for ($i = 0, $i_max = count($value); $i < $i_max; $i++) {
      $this->types[$i]->verifyValue($value[$i]);
    }
  }

  protected function hasInstanceInside(): bool {
    return in_array(true, array_map(fn(PhpDocType $type) => $type->hasInstanceInside(), $this->types), true);
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

  public function isNullAllowed(): bool {
    return false;
  }

  public function fromJson(\KPHP\JsonSerialization\JsonPath $json_path, $v, string $json_encoder) {
    throw new KphpJsonDecodeException("tuples are not supported in json: $json_path");
  }
}

