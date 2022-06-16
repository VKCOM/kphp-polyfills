<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\MsgPackSerialization;

use ReflectionException;
use RuntimeException;

class MsgPackSerializer {
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
      $current_value = $this->getValue($field, $instance);
      $this->checkTypeOf($field, $current_value);

      $this->tags_values[] = $field->id;
      $this->tags_values[] = $field->as_float32 ? new DeepForceFloat32($current_value) : $current_value;
    }

    ClassTransformer::$depth--;
  }

  /** @param mixed $value */
  private function checkTypeOf(FieldMetadata $field, $value): void {
    try {
      $field->phpdoc_type->verifyValue($value);
    } catch (RuntimeException $e) {
      if (ClassTransformer::$depth > ClassTransformer::$max_depth) {
        throw $e;
      }
      $class_name = $this->instance_metadata->klass->name;
      throw new RuntimeException("in field $class_name::\${$field->name}: " . $e->getMessage());
    }
  }

  /**
   * @return mixed
   * @throws ReflectionException
   */
  private function getValue(FieldMetadata $field, object $instance) {
    $property = $this->instance_metadata->klass->getProperty($field->name);
    $property->setAccessible(true);
    return $property->getValue($instance);
  }
}
