<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

require_once 'VariableNamingStyle.php';

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
  public function __construct(string $instance, string $encoder_name) {
    assert(is_string($instance) && $instance !== '' && $instance !== 'self');
    $this->reflection_of_instance = new ReflectionClass($instance);
    $this->use_resolver           = new UseResolver($this->reflection_of_instance);

    $classPhpdoc = $this->reflection_of_instance->getDocComment();
    $renamePolicy = self::parseFieldsRenameTag($classPhpdoc);
    if ($renamePolicy === 'none') {
      $renamePolicy = self::parseFieldsRenameEncoder($encoder_name);
    }

    $unique_names = [];
    foreach ($this->reflection_of_instance->getProperties() as $property) {
      $curDocComment = $property->getDocComment();
      $curName = $property->getName();

      $field = new FieldMetadata;
      $field->name = $curName;
      $field->rename = preg_match("/@kphp-json rename=(\w+)/", $curDocComment, $matches) ? $matches[1] : "";
      $field->skip = (bool)preg_match("/@kphp-json skip/", $curDocComment);

      if ($field->skip && $field->rename) {
        throw new RuntimeException("Unable to use @kphp-json skip and @kphp-json rename together");
      }

      if (!$field->rename) {
        $field->rename = self::applyRenamePolicy($field->name, $renamePolicy);
      }

      $name = $field->rename ?: $field->name;
      self::checkFieldsDuplication($name, $unique_names);

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

  private static function checkFieldsDuplication(string $name, array &$unique_names): void {
    if (in_array($name, $unique_names)) {
      throw new RuntimeException("@kphp-json {$name} property met twice");
    } else {
      $unique_names[] = $name;
    }
  }

  private static function applyRenamePolicy(string $name, string $renamePolicy): string {
    if ($renamePolicy === "snake_case") {
      return transform_to_snake_case($name);
    }
    if ($renamePolicy === "camelCase") {
      return transform_to_camel_case($name);
    }
    return "";
  }

  private static function validateFieldsRename(string $policy): void {
    if (!in_array($policy, ["none", "snake_case", "camelCase"])) {
      throw new RuntimeException("allowed values for kphp-json fields_rename=none|snake_case|camelCase, got: {$policy}");
    }
  }

  private static function parseFieldsRenameTag(string $phpdoc): string {
    if (preg_match("/@kphp-json fields_rename=(\w+)/", $phpdoc, $matches)) {
      $policy = $matches[1];
      self::validateFieldsRename($policy);
      return $policy;
    }
    return "none";
  }

  private static function parseFieldsRenameEncoder(string $encoder_name): string {
    $policy = $encoder_name::fields_rename;
    self::validateFieldsRename($policy);
    return $policy;
  }
}
