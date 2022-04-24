<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

use ReflectionProperty;
use ReflectionException;
use RuntimeException;

class InstanceSerializer {
  /** @var string */
  public $encoder_name;

  /** @var object */
  public $instance;

  /** @var InstanceMetadata */
  public $instance_metadata;

  public function __construct(object $instance, string $encoder_name) {
    $this->encoder_name = $encoder_name;
    $this->instance = $instance;
    $this->instance_metadata = InstanceMetadataCache::getInstanceMetadata(get_class($instance), $encoder_name);
  }

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function encode(array &$map, int $float_precision = 0) {
    foreach ($this->instance_metadata->fields_data as $field) {
      if ($field->skip || $field->skip_as_private) {
        continue;
      }
      try {
        $reflection = $this->instance_metadata->reflection_of_instance;
        $property = get_class_property($reflection, $field->name);
        $value = self::getValue($property, $this->instance);
        if ($field->skip_if_default && $value === self::getPropertyDefaultValue($property)) {
          continue;
        }
        $value = $field->phpdoc_type->encodeValue($value, $this->encoder_name,
          $this->instance_metadata->use_resolver, $field->float_precision ?: $float_precision);
        $map[$field->rename ?: $field->name] = $value;
      } catch (RuntimeException $e) {
        throw new RuntimeException("in field: `{$field->name}` -> " . $e->getMessage(), 0);
      }
    }
  }

  /**
   * @return mixed
   * @throws ReflectionException
   * @throws RuntimeException
   */
  private static function getValue(ReflectionProperty $property, object $instance) {
    $property->setAccessible(true);
    return $property->isInitialized($instance) ? $property->getValue($instance) : null;
  }

  private static function getPropertyDefaultValue(ReflectionProperty $property) {
    $properties = $property->getDeclaringClass()->getDefaultProperties();
    $default = $properties[$property->name] ?? null;
    return $default;
  }
}
