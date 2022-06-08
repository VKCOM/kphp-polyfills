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
use ReflectionProperty;
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

  /** @var bool */
  public $flatten_class = false;

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
      [$fields_data, $this->flatten_class] = self::readCurrentClassProperties($reflection, $encoder_name);
      array_push($this->fields_data, ...$fields_data);
      $reflection = $reflection->getParentClass();
    }
  }

  private static function checkKphpJsonTag(ReflectionProperty $property): void {
    if (preg_match("/@kphp-json\s/", $property->getDocComment())) {
      throw new RuntimeException("@kphp-json is allowed only for instance fields: {$property->name}");
    }
  }

  private static function isStaticProperty(ReflectionClass $reflection, ReflectionProperty $property): bool {
    return array_key_exists($property->getName(), $reflection->getStaticProperties());
  }

  private static function getCurrentClassProperties(ReflectionClass $ref)  {
    $all_props = $ref->getProperties();
    $base_props = $ref->getParentClass() ? $ref->getParentClass()->getProperties() : [];
    return array_diff($all_props, $base_props);
  }

  private static function readCurrentClassProperties(ReflectionClass $reflection, string $encoder_name) {
    $classPhpdoc = $reflection->getDocComment();
    $renamePolicy = self::parseFieldsRenameTag($classPhpdoc);
    if ($renamePolicy === 'none') {
      $renamePolicy = self::parseFieldsRenameEncoder($encoder_name);
    }

    $flatten_class = self::parseFlattenTag($reflection, $classPhpdoc);
    $skip_private_fields = self::skipPrivateFieldsTag($classPhpdoc) || (!$flatten_class && self::skipPrivateFieldsEncoder($encoder_name));
    $class_skip_if_default = self::parseSkipIfDefaultTag($classPhpdoc) || (!$flatten_class && self::parseSkipIfDefaultEncoder($encoder_name));
    $class_float_precision = self::parseFloatPrecisionTag($classPhpdoc) ?: self::parseFloatPrecisionEncoder($encoder_name);

    $fields_data = [];
    $unique_names = [];
    foreach (self::getCurrentClassProperties($reflection) as $property) {
      if (self::isStaticProperty($reflection, $property)) {
        self::checkKphpJsonTag($property);
        continue;
      }

      $curDocComment = $property->getDocComment();
      $curName = $property->getName();

      $field = new FieldMetadata;
      $field->name = $curName;
      $field->rename = preg_match("/@kphp-json rename=(\w+)/", $curDocComment, $matches) ? $matches[1] : "";
      $field->skip = (bool)preg_match("/@kphp-json skip\s+/", $curDocComment);
      $field->array_as_hashmap = (bool)preg_match("/@kphp-json array_as_hashmap\s+/", $curDocComment);
      $field->required = (bool)preg_match("/@kphp-json required\s+/", $curDocComment);
      $field->raw_string = (bool)preg_match("/@kphp-json raw_string\s+/", $curDocComment);
      $field->skip_as_private = $skip_private_fields && !$property->isPublic();
      $field->skip_if_default = self::parseSkipIfDefaultTag($curDocComment);
      $field->float_precision = self::parseFloatPrecisionTag($curDocComment);

      if ($field->skip && ($field->rename || $field->skip_if_default ||
          $field->float_precision || $field->array_as_hashmap || $field->required || $field->raw_string)) {
        throw new RuntimeException("'skip' can't be used together with other @kphp-json tags");
      }

      $field->rename = $field->rename ?: self::applyRenamePolicy($field->name, $renamePolicy);
      $field->skip_if_default = $field->skip_if_default || $class_skip_if_default;
      $field->float_precision = $field->float_precision ?: $class_float_precision;

      if ($flatten_class && ($field->rename || $field->skip || $field->skip_if_default ||
          $field->required || $field->raw_string || $skip_private_fields)) {
        throw new RuntimeException("'rename|fields_rename|fields_visibility|skip|skip_if_default|required|raw_string' " .
          "can't be used inside @kphp-json flatten class");
      }

      $name = $field->rename ?: $field->name;
      self::checkFieldsDuplication($name, $unique_names);

      $field->type = self::parsePropertyType($property);
      $field->phpdoc_type = self::parsePHPDocType($field->type, $property);

      if ($field->raw_string && $field->type !== 'string') {
        throw  new RuntimeException("@kphp-json raw_string tag are only allowed above string type, field name {$field->name}");
      }

      $fields_data[] = $field;
    }
    return [$fields_data, $flatten_class];
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

  private static function parseSkipIfDefaultTag(string $phpdoc): bool {
    return (bool)preg_match("/@kphp-json skip_if_default\s+/", $phpdoc);
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

  private static function parseFlattenTag(ReflectionClass $reflection, string $phpdoc): bool {
    $flatten_class = (bool)preg_match("/@kphp-json flatten\s+/", $phpdoc);
    if ($flatten_class && $reflection->getParentClass()) {
      throw new RuntimeException("class marked as @kphp-json flatten can't have parent. Class name {$reflection->getName()}");
    }
    if ($flatten_class && count($reflection->getProperties()) !== 1) {
      throw new RuntimeException("class marked as @kphp-json flatten should have only one field. Class name {$reflection->getName()}");
    }
    return $flatten_class;
  }
}
