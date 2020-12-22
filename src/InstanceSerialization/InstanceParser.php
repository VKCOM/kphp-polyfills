<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\InstanceSerialization;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

class InstanceParser {
  /**@var (mixed|DeepForceFloat32)[] */
  public $tags_values = [];

  /** @var InstanceMetadata */
  public $instance_metadata;

  /**
   * InstanceParser constructor.
   * @param object|string $instance
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function __construct($instance) {
    ClassTransformer::$depth++;
    if (ClassTransformer::$depth > ClassTransformer::$max_depth) {
      throw new RuntimeException('maximum depth of nested instances exceeded');
    }

    assert(is_object($instance) || (is_string($instance) && $instance !== '' && $instance !== 'self'));
    $this->instance_metadata = InstanceMetadataCache::getInstanceParser($instance);

    foreach ($this->instance_metadata->names as $i => $name) {
      $this->tags_values[] = $this->instance_metadata->field_ids[$i];
      $current_value = is_object($instance) ? $this->getValue($i, (object)$instance) : null;
      $this->tags_values[] = $current_value;

      if (is_object($instance)) {
        $this->checkTypeOf($this->instance_metadata->phpdoc_types[$i], $this->instance_metadata->types[$i], $this->instance_metadata->names[$i], $current_value);
      }
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
   * @param array|null $unpacked_arr
   * @return object|null
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function fromUnpackedArray($unpacked_arr): ?object {
    if ($unpacked_arr === null) {
      return null;
    }

    if (!is_array($unpacked_arr)) {
      throw new RuntimeException('Expected NIL or ARRAY type for unpacking class_instance');
    }

    $instance        = $this->instance_metadata->reflection_of_instance->newInstanceWithoutConstructor();
    $rc_for_instance = new ReflectionClass($instance);

    $is_even = static function($key) {
      return $key % 2 === 0;
    };
    $tags    = array_values(array_filter($this->tags_values, $is_even, ARRAY_FILTER_USE_KEY));

    for ($i = 0, $i_max = count($unpacked_arr); $i < $i_max; $i += 2) {
      $cur_tag   = (int)$unpacked_arr[$i];
      $cur_value = $unpacked_arr[$i + 1];

      $cur_idx  = array_search($cur_tag, $tags, true);
      if ($cur_idx === false) {
        continue;
      }
      $cur_type = $this->instance_metadata->phpdoc_types[$cur_idx];
      $cur_name = $this->instance_metadata->names[$cur_idx];

      $cur_value = $cur_type->fromUnpackedValue($cur_value, $this->instance_metadata->use_resolver);
      $this->setValue($rc_for_instance->getProperty($cur_name), $instance, $cur_value);
    }

    return $instance;
  }

  /**
   * @return mixed|DeepForceFloat32
   * @throws ReflectionException
   */
  private function getValue(int $property_id, object $instance) {
    $property = $this->instance_metadata->reflection_of_instance->getProperty($this->instance_metadata->names[$property_id]);
    $is_accessible = $property->isPrivate() || $property->isProtected();
    $property->setAccessible(true);
    $result = $property->getValue($instance);
    $property->setAccessible($is_accessible);

    if ($this->instance_metadata->as_float32[$property_id]) {
      return new DeepForceFloat32($result);
    }
    return $result;
  }

  private function setValue(ReflectionProperty $property, object $instance, $value): void {
    $is_accessible = $property->isPrivate() || $property->isProtected();
    $property->setAccessible(true);
    $property->setValue($instance, $value);
    $property->setAccessible($is_accessible);
  }
}
