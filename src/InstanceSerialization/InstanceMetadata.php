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
use RuntimeException;

class InstanceMetadata {

  /**@var string[] */
  public $names = [];

  /**@var string[] */
  public $types = [];

  /**@var PHPDocType[] */
  public $phpdoc_types = [];

  /**@var ReflectionClass|null */
  public $reflection_of_instance = null;

  /**@var UseResolver|null */
  public $use_resolver = null;

  /** @var int[] */
  public $field_ids = [];

  /**@var bool[] */
  public $as_float32 = [];

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function __construct(string $instance) {
    assert(is_string($instance) && $instance !== '' && $instance !== 'self');
    $this->reflection_of_instance = new ReflectionClass($instance);
    $this->use_resolver           = new UseResolver($this->reflection_of_instance);

    if (strpos($this->reflection_of_instance->getDocComment(), '@kphp-serializable') === false) {
      throw new RuntimeException('add @kphp-serializable phpdoc to class: ' . $this->reflection_of_instance->getName());
    }

    if ($this->reflection_of_instance->isAbstract() || $this->reflection_of_instance->isInterface() ||
      ($this->reflection_of_instance->getParentClass() && $this->reflection_of_instance->getParentClass()->getProperties())) {
      throw new RuntimeException('You may not serialize interfaces/abstract classes/polymorphic classes: ' . $this->reflection_of_instance->getName());
    }

    preg_match('/@kphp-reserved-fields\s+\[(\d+)\s*(?:,\s*(\d+))*]/', $this->reflection_of_instance->getDocComment(), $reserved_field_ids);
    array_shift($reserved_field_ids);
    $reserved_field_ids = array_map('intval', $reserved_field_ids);

    foreach ($this->reflection_of_instance->getProperties() as $property) {
      preg_match('/@kphp-serialized-field\s+(\d+|none)[\s*]/', $property->getDocComment(), $matches);

      if ($property->isStatic()) {
        if ($matches) {
          throw new RuntimeException('@kphp-serialized-field tag is forbidden for static fields: ' . $property->getName());
        }
        continue;
      }

      if (count($matches) <= 1) {
        throw new RuntimeException('You should add @kphp-serialized-field phpdoc to field: ' . $property->getName());
      }

      if ($matches[1] === 'none') {
        continue;
      }
      assert(is_numeric($matches[1]));
      $matches[1] = (int)$matches[1];

      if ($matches[1] < 0 || 127 < $matches[1]) {
        throw new RuntimeException("id=${matches[1]} is not in the range [0, 127], field: " . $property->getName());
      }

      if (in_array($matches[1], $reserved_field_ids, true)) {
        throw new RuntimeException("id=${matches[1]} is already in use, field: " . $property->getName());
      }
      $reserved_field_ids[] = $matches[1];
      $this->field_ids[] = $matches[1];

      $this->names[] = $property->getName();

      $this->as_float32[] = strpos($property->getDocComment(), '@kphp-serialized-float32') !== false;

      // get type either from @var or from php 7.4 field type hint
      $type = '';
      preg_match('/@var\s+([^\n]+)/', $property->getDocComment(), $matches);
      if (count($matches) > 1) {
        $type = (string)$matches[1];
      } else if (PHP_VERSION_ID >= 70400 && $property->hasType()) {
        $type = ($property->getType()->allowsNull() ? '?' : '') . $property->getType();
      }
      if ($type === '') {
        throw new RuntimeException("Can't detect type of field {$property->getName()}");
      }

      $type_copy = $type;
      $parsed_phpdoc = PHPDocType::parse($type_copy);
      if ($parsed_phpdoc === null) {
        throw new RuntimeException("Can't parse phpdoc of field {$property->getName()}: {$type}");
      }
      $this->types[] = $type;
      $this->phpdoc_types[] = $parsed_phpdoc;
    }
  }
}
