<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\MsgPackSerialization;

use ReflectionException;

class InstanceMetadataCache {

  /** @var InstanceMetadata[] */
  private static array $cached_classes = [];

  /** @throws ReflectionException */
  public static function getInstanceMetadata(string $class_name): InstanceMetadata {
    return self::$cached_classes[$class_name] ??= new InstanceMetadata($class_name);
  }
}
