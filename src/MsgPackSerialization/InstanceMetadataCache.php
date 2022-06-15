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
  private static $instance_parsers = [];

  /** @throws ReflectionException */
  public static function getInstanceMetadata(string $class_name): InstanceMetadata {
    if (!array_key_exists($class_name, self::$instance_parsers)) {
      self::$instance_parsers[$class_name] = new InstanceMetadata($class_name);
    }
    return self::$instance_parsers[$class_name];
  }
}
