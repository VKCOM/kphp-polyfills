<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

require_once 'Utils.php';

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

    $reflection = new ReflectionClass($instance);
    $this->reflection_of_instance = $reflection;
    $this->use_resolver = new UseResolver($this->reflection_of_instance);

    while ($reflection) {
      $fields_data = self::read_current_class_properties($reflection, $encoder_name);
      array_push($this->fields_data, ...$fields_data);
      $reflection = $reflection->getParentClass();
    }
  }

  private static function get_current_class_properties(ReflectionClass $ref)  {
    $all_props = $ref->getProperties();
    $base_props = $ref->getParentClass() ? $ref->getParentClass()->getProperties() : [];
    return array_diff($all_props, $base_props);
  }

  /** @return FieldMetadata[] */
  private static function read_current_class_properties(ReflectionClass $reflection, string $encoder_name) {
    $classPhpdoc = $reflection->getDocComment();
    $renamePolicy = self::parseFieldsRenameTag($classPhpdoc);
    if ($renamePolicy === 'none') {
      $renamePolicy = self::parseFieldsRenameEncoder($encoder_name);
    }

    $skip_private_fields = self::skipPrivateFieldsTag($classPhpdoc) || self::skipPrivateFieldsEncoder($encoder_name);
    $class_skip_if_default = self::parseSkipIfDefaultTag($classPhpdoc) || self::parseSkipIfDefaultEncoder($encoder_name);
    $class_float_precision = self::parseFloatPrecisionTag($classPhpdoc) ?: self::parseFloatPrecisionEncoder($encoder_name);

    $fields_data = [];
    $unique_names = [];
    foreach (self::get_current_class_properties($reflection) as $property) {
      $curDocComment = $property->getDocComment();
      $curName = $property->getName();

      $field = new FieldMetadata;
      $field->name = $curName;
      $field->rename = preg_match("/@kphp-json rename=(\w+)/", $curDocComment, $matches) ? $matches[1] : "";
      $field->skip = (bool)preg_match("/@kphp-json skip\s+/", $curDocComment);
      $field->array_as_hashmap = (bool)preg_match("/@kphp-json array_as_hashmap\s+/", $curDocComment);
      $field->skip_as_private = $skip_private_fields && !$property->isPublic();
      $field->skip_if_default = self::parseSkipIfDefaultTag($curDocComment);
      $field->float_precision = self::parseFloatPrecisionTag($curDocComment);

      if ($field->skip && ($field->rename || $field->skip_if_default || $field->float_precision || $field->array_as_hashmap)) {
        throw new RuntimeException("'skip' can't be used together with 'rename|skip_if_default|float_precision|array_as_hashmap' in kphp-json tag");
      }

      $field->rename = $field->rename ?: self::applyRenamePolicy($field->name, $renamePolicy);
      $field->skip_if_default = $field->skip_if_default || $class_skip_if_default;
      $field->float_precision = $field->float_precision ?: $class_float_precision;

      $name = $field->rename ?: $field->name;
      self::checkFieldsDuplication($name, $unique_names);

      $field->type = self::parsePropertyType($property);
      $field->phpdoc_type = self::parsePHPDocType($field->type, $property);

      $fields_data[] = $field;
    }
    return $fields_data;
  }

  private static function parsePropertyType($property): string {
    $res_type = '';
    // get type either from @var or from php 7.4 field type hint
    preg_match('/@var\s+([^\n]+)/', $property->getDocComment(), $matches);
    if (count($matches) > 1) {
      $res_type = (string)$matches[1];
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

      $res_type = $type_name;
    }
    if ($res_type === '') {
      throw new RuntimeException("Can't detect type of field {$property->getName()}");
    }
    return $res_type;
  }

  private static function parsePHPDocType(string $type, $property): ?PHPDocType {
    $type_copy = $type;
    $phpdoc_type = PHPDocType::parse($type_copy);
    if ($phpdoc_type === null) {
      throw new RuntimeException("Can't parse phpdoc of field {$property->getName()}: {$type}");
    }
    return $phpdoc_type;
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

  private static function validateFieldsVisibility(string $visibility): void {
    if (!in_array($visibility, ["all", "public"])) {
      throw new RuntimeException("allowed values for kphp-json fields_visibility=all|public, got: {$visibility}");
    }
  }

  private static function skipPrivateFieldsTag(string $phpdoc): bool {
    if (preg_match("/@kphp-json fields_visibility=(\w+)/", $phpdoc, $matches)) {
      $visibility = $matches[1];
      self::validateFieldsVisibility($visibility);
      return $visibility === 'public';
    }
    return false;
  }

  private static function skipPrivateFieldsEncoder(string $encoder_name): bool {
    $visibility = $encoder_name::fields_visibility;
    self::validateFieldsVisibility($visibility);
    return $visibility === 'public';
  }

  private static function validateSkipIfDefault(string $skip): void {
    if (!in_array($skip, ["true", "false"])) {
      throw new RuntimeException("allowed values for kphp-json skip_if_default=true|false, got: {$skip}");
    }
  }

  private static function parseSkipIfDefaultTag(string $phpdoc): bool {
    if (preg_match("/@kphp-json skip_if_default=(\w+)/", $phpdoc, $matches)) {
      $skip = $matches[1];
      self::validateSkipIfDefault($skip);
      return $skip === 'true';
    }
    return false;
  }

  private static function parseSkipIfDefaultEncoder(string $encoder_name): bool {
    return $encoder_name::skip_if_default;
  }

  private static function validateFloatPrecision(int $precision): void {
    if ($precision < 0) {
      throw new RuntimeException("kphp-json float_precision should be non negative integer, got: {$precision}");
    }
  }

  private static function parseFloatPrecisionTag(string $phpdoc): int {
    if (preg_match("/@kphp-json float_precision=(-?\d+)/", $phpdoc, $matches)) {
      $precision = (int)$matches[1];
      self::validateFloatPrecision($precision);
      return $precision;
    }
    return 0;
  }

  private static function parseFloatPrecisionEncoder(string $encoder_name): int {
    $precision = $encoder_name::float_precision;
    self::validateFloatPrecision($precision);
    return $precision;
  }
}
