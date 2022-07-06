<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2022 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

require_once 'JsonUtils.php';
require_once 'JsonExceptions.php';

use ReflectionClass;
use KPHP\PhpDocParsing\UseResolver;
use KPHP\PhpDocParsing\PhpDocType;

class InstanceReflectedForJson {
  /** @var FieldJsonSettings[] [field_name => settings, ... ], only of this class (parent fields are not listed) */
  public array $fields = [];
  public string $class_name;
  public bool $flatten_class = false;
  public ?KphpJsonTagList $kphp_json_tags = null;
  public ?InstanceReflectedForJson $parent = null;

  private ReflectionClass $klass;
  private UseResolver $use_resolver;


  /** @var self[] */
  private static array $cached_classes = [];

  public static function getCachedOrCreate(string $class_name, string $json_encoder): self {
    $key = $class_name . '_' . $json_encoder;
    return self::$cached_classes[$key] ??= new self($class_name, $json_encoder);
  }


  private function __construct(string $class_name, string $json_encoder) {
    $this->class_name = $class_name;
    $this->klass = new ReflectionClass($class_name);
    $this->use_resolver = new UseResolver($this->klass);
    $this->parent = $this->klass->getParentClass() ? self::getCachedOrCreate($this->klass->getParentClass()->name, $json_encoder) : null;

    try {
      $this->kphp_json_tags = KphpJsonTagList::create_from_phpdoc($this->use_resolver, $this->klass->getDocComment());
    } catch (\Exception $ex) {
      throw new \RuntimeException("Error at class $class_name: {$ex->getMessage()}");
    }

    $kphp_json_tags = $this->kphp_json_tags;
    $this->flatten_class = $kphp_json_tags && $kphp_json_tags->find_tag(fn(KphpJsonTag $tag) => $tag->attr_type === JsonAttrType::flatten && $tag->value);

    foreach ($this->klass->getProperties() as $property) {
      try {
        if ($property->isStatic() || $property->class !== $this->klass->name) { // filter out static and parent
          continue;
        }

        $field_json_tags = KphpJsonTagList::create_from_phpdoc($this->use_resolver, $property->getDocComment());
        $type = $this->get_field_type_from_var_phpdoc($property);
        $field = FieldJsonSettings::merge_and_inherit_json_tags($field_json_tags, $kphp_json_tags, $json_encoder, $property, $type);

        $this->fields[$property->name] = $field;
      } catch (\Exception $ex) {
        trigger_error("Error at field $class_name::\${$property->name}: {$ex->getMessage()}", E_USER_WARNING);
        throw new \RuntimeException("Error at field $class_name::\${$property->name}: {$ex->getMessage()}");
      }
    }

    if ($this->flatten_class && count($this->fields) !== 1) {
      throw new KphpJsonParsingException("Flatten class $class_name must have exactly one field");
    }
  }


  private function get_field_type_from_var_phpdoc(\ReflectionProperty $property): ?PhpDocType {
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
      throw new \RuntimeException("no @var above");
    }
    $type = PhpDocType::parse($res_type, $this->use_resolver);
    if ($type === null) {
      throw new \RuntimeException("@var has unsupported or invalid format");
    }
    return $type;
  }


  public function newInstanceWithoutConstructor(): object {
    return $this->klass->newInstanceWithoutConstructor();
  }

  public function getProperty(string $name): \ReflectionProperty {
    if (!$this->klass->hasProperty($name)) {
      throw new \RuntimeException("there is no field \$$name in class {$this->klass->name}");
    }

    $property = $this->klass->getProperty($name);
    $property->setAccessible(true);
    return $property;
  }
}
