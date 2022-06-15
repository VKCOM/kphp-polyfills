<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC �V Kontakte�
// Distributed under the GPL v3 License, see LICENSE.notice.txt

namespace KPHP\MsgPackSerialization;

use KPHP\PhpDocParsing\PhpDocType;

class FieldMetadata {
  /** @var int */
  public $id = 0;

  /**@var string */
  public $name = "";

  /**@var string */
  public $type = "";

  /**@var PHPDocType */
  public $phpdoc_type = null;

  /**@var bool */
  public $as_float32 = false;
}
