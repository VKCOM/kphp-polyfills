<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

use ReflectionException;

class InstanceMetadataCache {

  /** @var InstanceMetadata[] */
  private static $instance_parsers = [];

  /** @throws ReflectionException */
  public static function getInstanceMetadata(string $class_name, string $encoder_name): InstanceMetadata {
    $key = $class_name . '_' . $encoder_name;
    if (!array_key_exists($key, self::$instance_parsers)) {
      self::$instance_parsers[$key] = new InstanceMetadata($class_name, $encoder_name);
    }
    return self::$instance_parsers[$key];
  }
}
