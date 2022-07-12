<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2022 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

use KPHP\PhpDocParsing\PhpDocType;

class JsonDeserializer {
  public string $json_encoder;
  public InstanceReflectedForJson $reflected;

  public function __construct(string $class_name, string $json_encoder) {
    assert($class_name !== '' && $class_name !== 'self');

    $this->json_encoder = $json_encoder;
    $this->reflected = InstanceReflectedForJson::getCachedOrCreate($class_name, $json_encoder);
  }

  /**
   * @throws KphpJsonDecodeException
   * @throws \JsonException
   */
  public function unserializeInstanceFromJson(string $json_string): ?object {
    $map = json_decode($json_string, false, 512, JSON_THROW_ON_ERROR);
    if (!($map instanceof \stdClass) && !$this->reflected->flatten_class) {
      throw new KphpJsonDecodeException("root element of json string must be an object type, got " . gettype($map));
    }

    return $this->decodeAndReturnInstance(new JsonPath(), $map);
  }

  public function decodeAndReturnInstance(JsonPath $json_path, $v): ?object {
    if ($this->reflected->flatten_class) {
      $instance = $this->reflected->newInstanceWithoutConstructor();
      $this->decodeFlattenClassSingleField($json_path, $v, $instance);
      $this->callWakeupMagicMethodIfExists($instance);
      return $instance;
    }

    if ($v === null) {
      return null;
    }
    if (!($v instanceof \stdClass)) {
      throw new KphpJsonDecodeException("unexpected type " . gettype($v) . " for key $json_path");
    }

    $instance = $this->reflected->newInstanceWithoutConstructor();
    $this->decodeRegularClassFields($json_path, $v, $instance);
    for ($parent = $this->reflected->parent; $parent; $parent = $parent->parent) {
      $p_deserializer = new JsonDeserializer($parent->class_name, $this->json_encoder);
      $p_deserializer->decodeRegularClassFields($json_path, $v, $instance);
    }
    $this->callWakeupMagicMethodIfExists($instance);
    return $instance;
  }

  private function decodeFlattenClassSingleField(JsonPath $json_path, $v, object $into_instance) {
    $field = array_first_value($this->reflected->fields);
    $property = $this->reflected->getProperty($field->field_name);
    $property->setValue($into_instance, $this->decodeValue($json_path, $v, $field->raw_string, $field->type));
  }

  private function decodeRegularClassFields(JsonPath $json_path, $v, object $into_instance) {
    foreach ($this->reflected->fields as $field) {
      if ($field->skip_when_decoding) {
        continue;
      }
      $property = $this->reflected->getProperty($field->field_name);

      $json_key = $field->json_key;
      $json_path->enter($json_key);
      if (!property_exists($v, $json_key)) {
        if ($field->required) {
          throw new KphpJsonDecodeException("absent required field $json_path");
        }
        $def_val = JsonUtils::property_get_default_value($property);
        if ($def_val === null && !$field->type->isNullAllowed()) {
          // untyped property without a default, like `public array $ids`, explicitly marked with `required = false`
          continue;
        }
        $property->setValue($into_instance, $def_val);
      } else {
        $property->setValue($into_instance, $this->decodeValue($json_path, $v->$json_key, $field->raw_string, $field->type));
      }
      $json_path->leave();
    }
  }

  /** @return mixed|object */
  private function decodeValue(JsonPath $json_path, $json_value, bool $raw_string, PhpDocType $field_type) {
    if ($raw_string) {
      return json_encode($json_value);
    }

    return $field_type->fromJson($json_path, $json_value, $this->json_encoder);
  }

  private function callWakeupMagicMethodIfExists(object $instance) {
    if (method_exists($instance, '__wakeup')) {
      $instance->__wakeup();
    }
  }
}
