<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

class InstanceDeserializer {
  /** @var InstanceMetadata */
  public $instance_metadata;

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function __construct(string $class_name) {
    assert($class_name !== '' && $class_name !== 'self');
    $this->instance_metadata = InstanceMetadataCache::getInstanceMetadata($class_name);
  }

  public function decode(array $map) : object {
    $instance = $this->instance_metadata->reflection_of_instance->newInstanceWithoutConstructor();
    $reflection = new ReflectionClass($instance);

    foreach ($this->instance_metadata->fields_data as $field) {
      $name = $field->rename ?: $field->name;
      $value = $map[$name] ?? null;

      $value = $field->phpdoc_type->decodeValue($value, $this->instance_metadata->use_resolver);

      $property = $reflection->getProperty($field->name);
      $property->setAccessible(true);
      if ($value !== null || ($property->hasType() && $property->getType()->allowsNull())) {
        $property->setValue($instance, $value);
      }
    }
    return $instance;
  }
}
