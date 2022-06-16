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
  public PhpDocType $type1;
  public PhpDocType $type2;

  protected static function parseImpl(string &$str, UseResolver $use_resolver): ?PhpDocType {
    if (parent::removeIfStartsWith($str, '|')) {
      $type2 = PhpDocType::parse($str, $use_resolver);
      if ($type2 === null) {
        return null;
      }
      $res        = new self();
      $res->type2 = $type2;
      return $res;
    }

    return null;
  }

  public function fromUnpackedValue($value) {
    try {
      return $this->type1->fromUnpackedValue($value);
    } catch (Throwable $_) {
      return $this->type2->fromUnpackedValue($value);
    }
  }

  public function verifyValue($value): void {
    try {
      $this->type1->verifyValue($value);
    } catch (Throwable $_) {
      $this->type2->verifyValue($value);
    }
  }

  protected function hasInstanceInside(): bool {
    return $this->type1->hasInstanceInside() || $this->type2->hasInstanceInside();
  }
}
