<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

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
  public function encode(array &$map) {
    foreach ($this->instance_metadata->fields_data as $field) {
      if ($field->skip) {
        continue;
      }
      try {
        $value = $this->getValue($field, $this->instance);
        $field->phpdoc_type->storeValueToMap($field->rename ?: $field->name, $value, $map, $this->encoder_name, $this->instance_metadata->use_resolver);
      } catch (RuntimeException $e) {
        throw new RuntimeException("in field: `{$field->name}` -> " . $e->getMessage(), 0);
      }
    }
  }

  /**
   * @return mixed
   * @throws ReflectionException
   */
  private function getValue(FieldMetadata $field, object $instance) {
    $property = $this->instance_metadata->reflection_of_instance->getProperty($field->name);
    $property->setAccessible(true);
    return $property->isInitialized($instance) ? $property->getValue($instance) : null;
  }
}
