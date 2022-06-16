<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\MsgPackSerialization;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

class MsgPackDeserializer {
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

    $instance        = $this->instance_metadata->klass->newInstanceWithoutConstructor();
    $rc_for_instance = new ReflectionClass($instance);

    for ($i = 0, $i_max = count($unpacked_arr); $i < $i_max; $i += 2) {
      $cur_tag   = (int)$unpacked_arr[$i];
      $value = $unpacked_arr[$i + 1];

      foreach ($this->instance_metadata->fields_data as $field) {
        if ($field->id == $cur_tag) {
          $value = $field->phpdoc_type->fromUnpackedValue($value);

          $property = $rc_for_instance->getProperty($field->name);
          $property->setAccessible(true);
          $property->setValue($instance, $value);
          break;
        }
      }
    }

    return $instance;
  }
}
