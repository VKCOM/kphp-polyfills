<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\MsgPackSerialization;

use KPHP\PhpDocParsing\PhpDocType;
use KPHP\PhpDocParsing\UseResolver;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class InstanceMetadata {
  /**@var FieldMetadata[] */
  public array $fields_data = [];

  public ReflectionClass $klass;
  public UseResolver $use_resolver;

  /**
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function __construct(string $class_name) {
    assert($class_name !== '' && $class_name !== 'self');
    $this->klass = new ReflectionClass($class_name);
    $this->use_resolver = new UseResolver($this->klass);

    if (strpos($this->klass->getDocComment(), '@kphp-serializable') === false) {
      throw new RuntimeException('add @kphp-serializable phpdoc to class: ' . $this->klass->getName());
    }

    if ($this->klass->isAbstract() || $this->klass->isInterface() ||
      ($this->klass->getParentClass() && $this->klass->getParentClass()->getProperties())) {
      throw new RuntimeException('You may not serialize interfaces/abstract classes/polymorphic classes: ' . $this->klass->getName());
    }

    preg_match('/@kphp-reserved-fields\s+\[(\d+)\s*(?:,\s*(\d+))*]/', $this->klass->getDocComment(), $reserved_field_ids);
    array_shift($reserved_field_ids);
    $reserved_field_ids = array_map('intval', $reserved_field_ids);

    foreach ($this->klass->getProperties() as $property) {
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

      try {
        if ($field->type === '') {
          throw new RuntimeException("no @var above");
        }
        $type_copy = $field->type;

        $field->phpdoc_type = PHPDocType::parse($type_copy, $this->use_resolver);
        if ($field->phpdoc_type === null) {
          throw new RuntimeException("@var has invalid or unsupported format");
        }
      } catch (\Exception $ex) {
        throw new RuntimeException("Error parsing phpdoc of field $class_name::\$$curName: {$ex->getMessage()}");
      }
      $this->fields_data[] = $field;
    }
  }
}
