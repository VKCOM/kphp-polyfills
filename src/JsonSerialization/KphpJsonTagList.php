<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

// if a class/field has at least one @kphp-json above,
// this class containing all such tags from phpdoc is created
// similar to KphpJsonTagList in KPHP
class KphpJsonTagList {
  /** @var KphpJsonTag[] */
  public array $tags = [];

  function find_tag(callable $callback): ?KphpJsonTag {
    // no-'for' tag appears above 'for', so find the last one satisfying
    $last = null;
    foreach ($this->tags as $tag) {
      if ($callback($tag)) {
        $last = $tag;
      }
    }
    return $last;
  }

  function add_tag(KphpJsonTag $json_tag) {
    // for simplicity, don't perform check for duplicates and validation (KPHP does this on compile, KPHPStorm also highlights)
    $this->tags[] = $json_tag;
  }

  /**
   * @param string|false $docComment
   */
  public static function create_from_phpdoc(\KPHP\PhpDocParsing\UseResolver $use_resolver, $docComment): ?KphpJsonTagList {
    if (!$docComment || false === strpos($docComment, '@kphp-json')) {
      return null;
    }

    $list = new KphpJsonTagList();
    $phpdoc = new \KPHP\PhpDocParsing\PhpDocComment($docComment);

    foreach ($phpdoc->get_all_tags() as $tag) {
      if ($tag->name === "@kphp-json") {
        $list->add_tag(KphpJsonTag::parse_from_doc_tag($use_resolver, trim($tag->value)));
      }
    }

    return $list;
  }
}
