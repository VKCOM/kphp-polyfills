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
use RuntimeException;

use KPHP\PhpDocTypeParsing\UseResolver;
use KPHP\PhpDocTypeParsing\PHPDocType;

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

    foreach ($this->reflection_of_instance->getProperties() as $property) {
      $curDocComment = $property->getDocComment();
      $curName = $property->getName();

      $field = new FieldMetadata;
      $field->name = $curName;

      if (preg_match("/@kphp-json rename=(\w+)/", $curDocComment, $matches)) {
        $field->rename = $matches[1];
      }
      $field->skip = (bool)preg_match("/@kphp-json skip/", $curDocComment);

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
