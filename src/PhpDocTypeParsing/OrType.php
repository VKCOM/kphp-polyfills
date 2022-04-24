<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocTypeParsing;

use Throwable;

class OrType extends PHPDocType {
  /**@var PHPDocType */
  public $type1;

  /**@var PHPDocType */
  public $type2;

  protected static function parseImpl(string &$str): ?PHPDocType {
    if (parent::removeIfStartsWith($str, '|')) {
      $res        = new self();
      $res->type2 = PHPDocType::parse($str);
      return $res;
    }

    return null;
  }

  public function fromUnpackedValue($value, UseResolver $use_resolver) {
    try {
      return $this->type1->fromUnpackedValue($value, $use_resolver);
    } catch (Throwable $_) {
      return $this->type2->fromUnpackedValue($value, $use_resolver);
    }
  }

  public function verifyValue($value, UseResolver $use_resolver): void {
    try {
      $this->type1->verifyValue($value, $use_resolver);
    } catch (Throwable $_) {
      $this->type2->verifyValue($value, $use_resolver);
    }
  }

  protected function hasInstanceInside(): bool {
    return $this->type1->hasInstanceInside() || $this->type2->hasInstanceInside();
  }

  protected function hasNullInside(): bool {
    return $this->type1->hasNullInside() || $this->type2->hasNullInside();
  }

  public function encodeValue($value, string $encoder_name, UseResolver $use_resolver, int $float_precision, bool $array_as_hashmap = false) {
    if ($value === null) {
      return $this->getDefaultValue();
    }

    try {
      return $this->type1->encodeValue($value, $encoder_name, $use_resolver, $float_precision, $array_as_hashmap);
    } catch (Throwable $_) {
      return $this->type2->encodeValue($value, $encoder_name, $use_resolver, $float_precision, $array_as_hashmap);
    }
  }

  protected function getDefaultValue() {
    if ($this->hasNullInside()) {
      return null;
    }

    try {
      return $this->type1->getDefaultValue();
    } catch (Throwable $_) {
      return $this->type2->getDefaultValue();
    }
  }

  public function decodeValue($value, string $encoder_name, UseResolver $use_resolver) {
    if ($value === null) {
      return $this->getDefaultValue();
    }

    try {
      return $this->type1->decodeValue($value, $encoder_name, $use_resolver);
    } catch (Throwable $_) {
      return $this->type2->decodeValue($value, $encoder_name, $use_resolver);
    }
  }
}
