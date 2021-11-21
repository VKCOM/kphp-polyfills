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
  /**@var FieldMetadata[] */
  public $fields_data = [];

  /**@var ?ReflectionClass */
  public $reflection_of_instance = null;

  /**@var ?UseResolver */
  public $use_resolver = null;

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
      $curDocComment = $property->getDocComment();
      $curName = $property->getName();
      preg_match('/@kphp-serialized-field\s+(\d+|none)[\s*]/', $curDocComment, $matches);

      if ($property->isStatic()) {
        if ($matches) {
          throw new RuntimeException('@kphp-serialized-field tag is forbidden for static fields: ' . $curName);
        }
        continue;
      }

      if (count($matches) <= 1) {
        throw new RuntimeException('You should add @kphp-serialized-field phpdoc to field: ' . $curName);
      }

      if ($matches[1] === 'none') {
        continue;
      }
      assert(is_numeric($matches[1]));
      $field = new FieldMetadata();
      $field->id = (int)$matches[1];

      if ($field->id < 0 || 127 < $field->id) {
        throw new RuntimeException("id=${matches[1]} is not in the range [0, 127], field: " . $curName);
      }

      if (in_array($field->id, $reserved_field_ids, true)) {
        throw new RuntimeException("id=${matches[1]} is already in use, field: " . $curName);
      }
      $reserved_field_ids[] = $field->id;

      $field->name = $curName;

      $field->as_float32 = strpos($curDocComment, '@kphp-serialized-float32') !== false;

      // get type either from @var or from php 7.4 field type hint
      preg_match('/@var\s+([^\n]+)/', $curDocComment, $matches);
      if (count($matches) > 1) {
        $field->type = (string)$matches[1];
      } else if (PHP_VERSION_ID >= 70400 && $property->hasType()) {
        $type = $property->getType();
        $type_name = $type->getName();

        if (!$type->isBuiltin() && $type_name[0] !== '\\') {
          // Fix for https://github.com/VKCOM/kphp-polyfills/issues/35
          // So UseResolver::resolveName will return the path as is.
          $type_name = "\\{$type_name}";
        }
        if ($type->allowsNull()) {
          $type_name = "?{$type_name}";
        }

        $field->type = $type_name;
      }
      if ($field->type === '') {
        throw new RuntimeException("Can't detect type of field {$curName}");
      }

      $type_copy = $field->type;
      $field->phpdoc_type = PHPDocType::parse($type_copy);
      if ($field->phpdoc_type === null) {
        throw new RuntimeException("Can't parse phpdoc of field {$curName}: {$field->type}");
      }
      $this->fields_data[] = $field;
    }
  }
}
