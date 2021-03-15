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

    foreach ($this->instance_metadata->names as $i => $name) {
      $this->tags_values[] = $this->instance_metadata->field_ids[$i];
      $current_value = $this->getValue($i, $instance);
      $this->tags_values[] = $current_value;

      $this->checkTypeOf($this->instance_metadata->phpdoc_types[$i], $this->instance_metadata->types[$i], $this->instance_metadata->names[$i], $current_value);
    }

    ClassTransformer::$depth--;
  }

  /**
   * @param mixed $value
   */
  private function checkTypeOf(PHPDocType $phpdoc_type, string $type, string $name, $value): void {
    try {
      $phpdoc_type->verifyValue($value, $this->instance_metadata->use_resolver);
    } catch (RuntimeException $e) {
      if (ClassTransformer::$depth > ClassTransformer::$max_depth) {
        throw $e;
      }
      $value = vk_json_encode($value);
      throw new RuntimeException("value: `${value}` from field: `${name}` doesn't correspond to type: `${type}`", 0, $e);
    }
  }

  /**
   * @return mixed|DeepForceFloat32
   * @throws ReflectionException
   */
  private function getValue(int $property_id, object $instance) {
    $property = $this->instance_metadata->reflection_of_instance->getProperty($this->instance_metadata->names[$property_id]);
    $property->setAccessible(true);
    $result = $property->getValue($instance);

    if ($this->instance_metadata->as_float32[$property_id]) {
      return new DeepForceFloat32($result);
    }
    return $result;
  }
}
