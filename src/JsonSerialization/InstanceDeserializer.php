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
use stdClass;

class InstanceDeserializer {
  /** @var string */
  public $encoder_name;

  /** @var InstanceMetadata */
  public $instance_metadata;

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function __construct(string $class_name, string $encoder_name) {
    $this->encoder_name = $encoder_name;
    assert($class_name !== '' && $class_name !== 'self');
    $this->instance_metadata = InstanceMetadataCache::getInstanceMetadata($class_name, $encoder_name);
  }

  public function decode($value) : object {
    $instance = $this->instance_metadata->reflection_of_instance->newInstanceWithoutConstructor();
    $reflection = new ReflectionClass($instance);

    $this->isFlattenClass() ?
      $this->decodeFlattenClass($value, $reflection, $instance) :
      $this->decodeRegularClass($value, $reflection, $instance);
    return $instance;
  }

  public function isFlattenClass(): bool {
    return $this->instance_metadata->flatten_class;
  }

  private function decodeRegularClass(stdClass $map, ReflectionClass $reflection, object $instance): void {
    foreach ($this->instance_metadata->fields_data as $field) {
      $name = $field->rename ?: $field->name;
      if (!property_exists($map, $name) && $field->required) {
        throw new RuntimeException("absent required field $field->name for class $reflection->name");
      }
      $value = $map->{$name} ?? null;
      $this->decodeImpl($field, $reflection, $instance, $value);
    }
  }

  private function decodeFlattenClass($value, ReflectionClass $reflection, object $instance): void {
    if (count($this->instance_metadata->fields_data) !== 1) {
      $class_name = $this->instance_metadata->reflection_of_instance->getName();
      throw new RuntimeException("flatten class should have only one field. Class name {$class_name}");
    }
    $this->decodeImpl($this->instance_metadata->fields_data[0], $reflection, $instance, $value);
  }

  private function decodeImpl(FieldMetadata $field, ReflectionClass $reflection, object $instance, $value): void {
    if ($field->skip || $field->skip_as_private) {
      # store default value in instance for skipped field
      $value = null;
    }
    $property = get_class_property($reflection, $field->name);
    if ($value === null && self::hasPropertyDefaultValue($property)) {
      return;
    }

    if ($field->raw_string) {
      $value = json_encode($value);
    }

    $value = $field->phpdoc_type->decodeValue($value, $this->encoder_name, $this->instance_metadata->use_resolver);

    $property->setAccessible(true);
    if ($value !== null || ($property->hasType() && $property->getType()->allowsNull())) {
      $property->setValue($instance, $value);
    }
  }

  private static function hasPropertyDefaultValue(ReflectionProperty $property): bool {
    $properties = $property->getDeclaringClass()->getDefaultProperties();
    $default = $properties[$property->name] ?? null;
    return $default !== null;
  }
}
