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
use Exception;

class RecursionDepthException extends Exception {}

class InstanceSerializer {
  /** @var string */
  public $encoder_name;

  /** @var object */
  public $instance;

  /** @var InstanceMetadata */
  public $instance_metadata;

  /** @var int */
  public static $depth = 0;
  /** @var int */
  public static $max_depth = 64;

  public function __construct(object $instance, string $encoder_name) {
    $this->encoder_name = $encoder_name;
    $this->instance = $instance;
    $this->instance_metadata = InstanceMetadataCache::getInstanceMetadata(get_class($instance), $encoder_name);
  }

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function encode(int $float_precision = 0) {
    return $this->isFlattenClass() ?
      $this->encodeFlattenClass($float_precision) :
      $this->encodeRegularClass($float_precision);
  }

  private function isFlattenClass(): bool {
    return $this->instance_metadata->flatten_class;
  }

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  private function encodeRegularClass(int $float_precision) {
    if (++self::$depth > self::$max_depth) {
      throw new RecursionDepthException("allowed depth=" . self::$max_depth . " of json object exceeded");
    }
    $map = [];
    foreach ($this->instance_metadata->fields_data as $field) {
      [$skip, $value] = $this->encodeImpl($field, $field->float_precision ?: $float_precision);
      if (!$skip) {
        $map[$field->rename ?: $field->name] = $value;
      }
    }
    --self::$depth;
    #serialize empty object as '{}', not as '[]'
    return $map ?: (object)[];
  }

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  private function encodeFlattenClass(int $float_precision) {
    if (count($this->instance_metadata->fields_data) !== 1) {
      $class_name = $this->instance_metadata->reflection_of_instance->getName();
      throw new RuntimeException("flatten class should have only one field. Class name {$class_name}");
    }
    $field = $this->instance_metadata->fields_data[0];
    [$_, $value] = $this->encodeImpl($field, $field->float_precision ?: $float_precision);
    return $value;
  }

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  private function encodeImpl(FieldMetadata $field, int $float_precision) {
    $skip = false;
    if ($field->skip || $field->skip_as_private) {
      $skip = true;
      return [$skip, null];
    }
    try {
      $reflection = $this->instance_metadata->reflection_of_instance;
      $property   = get_class_property($reflection, $field->name);
      $value      = self::getValue($property, $this->instance);
      if ($field->skip_if_default && $value === self::getPropertyDefaultValue($property)) {
        $skip = true;
        return [$skip, null];
      }
      $value = $field->phpdoc_type->encodeValue($value, $this->encoder_name,
        $this->instance_metadata->use_resolver, $float_precision, $field->array_as_hashmap);

      if ($field->raw_string) {
        $value = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
      }

      return [$skip, $value];
    } catch (RecursionDepthException $e) {
      throw $e;
    } catch (RuntimeException $e) {
      throw new RuntimeException("in field: `{$field->name}` -> " . $e->getMessage(), 0);
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
