<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

namespace KPHP\JsonSerialization;

use KPHP\PhpDocTypeParsing\PHPDocType;

class FieldMetadata {
  /**@var string */
  public $name = "";

  /**@var string */
  public $rename = "";

  /** @var bool */
  public $skip = false;

  /** @var bool */
  public $skip_as_private = false;

  /** @var bool */
  public $skip_if_default = false;

  /** @var int */
  public $float_precision = 0;

  /** @var bool */
  public $array_as_hashmap = false;

  /** @var bool */
  public $required = false;

  /**@var string */
  public $type = "";

  /**@var PHPDocType */
  public $phpdoc_type = null;
}
