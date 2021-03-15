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

class InstanceDeserializer {
  /** @var InstanceMetadata */
  public $instance_metadata;

  /**
   * InstanceParser constructor.
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function __construct(string $instance) {
    assert($instance !== '' && $instance !== 'self');
    $this->instance_metadata = InstanceMetadataCache::getInstanceMetadata($instance);
  }

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function fromUnpackedArray(?array $unpacked_arr): ?object {
    if ($unpacked_arr === null) {
      return null;
    }

    if (!is_array($unpacked_arr)) {
      throw new RuntimeException('Expected NIL or ARRAY type for unpacking class_instance');
    }

    $instance        = $this->instance_metadata->reflection_of_instance->newInstanceWithoutConstructor();
    $rc_for_instance = new ReflectionClass($instance);

    for ($i = 0, $i_max = count($unpacked_arr); $i < $i_max; $i += 2) {
      $cur_tag   = (int)$unpacked_arr[$i];
      $cur_value = $unpacked_arr[$i + 1];

      $cur_idx  = array_search($cur_tag, $this->instance_metadata->field_ids, true);
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

  private function setValue(ReflectionProperty $property, object $instance, $value): void {
    $property->setAccessible(true);
    $property->setValue($instance, $value);
  }
}
