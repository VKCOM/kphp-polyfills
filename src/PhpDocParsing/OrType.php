<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocParsing;

use Throwable;

class OrType extends PhpDocType {
  /**@var PhpDocType */
  public $type1;

  /**@var PhpDocType */
  public $type2;

  protected static function parseImpl(string &$str): ?PhpDocType {
    if (parent::removeIfStartsWith($str, '|')) {
      $res        = new self();
      $res->type2 = PhpDocType::parse($str);
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
}
