<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2022 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocParsing;

class PhpDocTag {
  public string $name;    // e.g. "@var" or "@kphp-json"
  public string $value;   // whole string after a name till end of line

  function __construct(string $name, string $value) {
    $this->name = $name;
    $this->value = $value;
  }
}
