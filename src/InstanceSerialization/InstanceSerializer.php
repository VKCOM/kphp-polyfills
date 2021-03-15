<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\InstanceSerialization;

use ReflectionException;
use RuntimeException;

class InstanceSerializer {
  /**@var (mixed|DeepForceFloat32)[] */
  public $tags_values = [];

  /** @var InstanceMetadata */
  public $instance_metadata;

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function __construct(object $instance) {
    ClassTransformer::$depth++;
    if (ClassTransformer::$depth > ClassTransformer::$max_depth) {
      throw new RuntimeException('maximum depth of nested instances exceeded');
    }

    $this->instance_metadata = InstanceMetadataCache::getInstanceMetadata(get_class($instance));

    foreach ($this->instance_metadata->fields_data as $field) {
      $this->tags_values[] = $field->id;
      $current_value = $this->getValue($field, $instance);
      $this->tags_values[] = $current_value;

      $this->checkTypeOf($field, $current_value);
    }

    ClassTransformer::$depth--;
  }

  /** @param mixed $value */
  private function checkTypeOf(FieldMetadata $field, $value): void {
    try {
      $field->phpdoc_type->verifyValue($value, $this->instance_metadata->use_resolver);
    } catch (RuntimeException $e) {
      if (ClassTransformer::$depth > ClassTransformer::$max_depth) {
        throw $e;
      }
      $value = vk_json_encode($value);
      throw new RuntimeException("value: `${value}` from field: `{$field->name}` doesn't correspond to type: `{$field->type}`", 0, $e);
    }
  }

  /**
   * @return mixed|DeepForceFloat32
   * @throws ReflectionException
   */
  private function getValue(FieldMetadata $field, object $instance) {
    $property = $this->instance_metadata->reflection_of_instance->getProperty($field->name);
    $property->setAccessible(true);
    $result = $property->getValue($instance);

    if ($field->as_float32) {
      return new DeepForceFloat32($result);
    }
    return $result;
  }
}
