<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\InstanceSerialization;

class InstanceMetadataCache {

  /** @var InstanceMetadata[] */
  private static $instance_parsers = [];

  /**
   * @param object|string $class_name
   * @return InstanceMetadata
   * @throws \ReflectionException
   */
  public static function getInstanceParser($class_name): InstanceMetadata {
    if (is_object($class_name)) {
      $class_name = get_class($class_name);
    }
    if (!array_key_exists($class_name, self::$instance_parsers)) {
      self::$instance_parsers[$class_name] = new InstanceMetadata($class_name);
    }
    return self::$instance_parsers[$class_name];
  }
}
