<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocParsing;

class PhpDocComment {
  /** @var PhpDocTag[] */
  private array $tags = [];

  // parsing algorithm is similar to PhpDocComment() constructor in KPHP
  function __construct(string $phpdoc_str) {
    $pos = 2;                         // after '/*'
    $len = strlen($phpdoc_str) - 2;   // before '*/'

    while ($pos < $len) {
      // we are at line start, waiting for '*' after spaces
      $c = $phpdoc_str[$pos];
      if ($c === ' ' || $c === "\t" || $c === "\n") {
        $pos++;
        continue;
      }
      // if not '*', skip this line
      if ($c !== '*') {
        while ($pos < $len && $phpdoc_str[$pos] !== "\n") {
          $pos++;
        }
        continue;
      }
      // wait for '@' after spaces
      $pos++;
      while ($pos < $len && $phpdoc_str[$pos] === ' ') {
        $pos++;
      }
      // if not @ after *, skip this line
      if ($phpdoc_str[$pos] !== '@') {
        while ($pos < $len && $phpdoc_str[$pos] !== "\n") {
          $pos++;
        }
        continue;
      }

      // $pos points to '@', read tag name until space
      $start = $pos;
      while ($pos < $len && $phpdoc_str[$pos] !== ' ' && $phpdoc_str[$pos] !== "\n" && $phpdoc_str[$pos] !== "\t") {
        $pos++;
      }
      $tag_name = substr($phpdoc_str, $start, $pos - $start);

      // after @tag-name, there are spaces and a tag value until end of line
      while ($pos < $len && $phpdoc_str[$pos] === ' ') {
        $pos++;
      }
      $start = $pos;
      while ($pos < $len && $phpdoc_str[$pos] !== "\n") {
        $pos++;
      }
      $tag_value = substr($phpdoc_str, $start, $pos - $start);

      $this->tags[] = new PhpDocTag($tag_name, $tag_value);
    }
  }

  /** @return PhpDocTag[] */
  function get_all_tags(): array {
    return $this->tags;
  }

  function has_tag(string $name): bool {
    foreach ($this->tags as $tag) {
      if ($tag->name === $name) {
        return true;
      }
    }
    return false;
  }

  function get_tag(string $name): ?PhpDocTag {
    foreach ($this->tags as $tag) {
      if ($tag->name === $name) {
        return $tag;
      }
    }
    return null;
  }
}
