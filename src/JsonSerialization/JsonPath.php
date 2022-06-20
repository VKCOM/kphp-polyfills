<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

// JsonPath represents a tree where decoding json pointer points to
// it's used to print out error messages when types are not satisfied: we print all the tree up
// for example {"a":{"b":12}}  (but expected string)
// would print "unexpected type string for key /['a']['b']"
class JsonPath {
  /** @var string[] */
  private array $arr = [];

  public function enter(?string $key) {
    $this->arr[] = $key;
  }

  public function leave() {
    array_pop($this->arr);
  }

  public function __toString(): string {
    $stringed = array_map(function(?string $key): string {
      return $key === null ? '[.]' : "['$key']";
    }, $this->arr);

    return '/' . implode('', $stringed);
  }
}
