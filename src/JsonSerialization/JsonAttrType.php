<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2022 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

// it's enum actually, but we are limited to PHP 7.4
// similar to enum JsonAttrType in KPHP, but in PHP we don't use bitmasks, just strings
class JsonAttrType {
  const unknown           = 'unknown';
  const rename            = 'rename';
  const skip              = 'skip';
  const array_as_hashmap  = 'array_as_hashmap';
  const raw_string        = 'raw_string';
  const required          = 'required';
  const float_precision   = 'float_precision';
  const skip_if_default   = 'skip_if_default';
  const visibility_policy = 'visibility_policy';
  const rename_policy     = 'rename_policy';
  const flatten           = 'flatten';
  const fields            = 'fields';
}
