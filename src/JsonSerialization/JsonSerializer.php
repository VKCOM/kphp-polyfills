<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2022 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

class JsonSerializer {
  public string $json_encoder;
  public ?object $instance;
  public InstanceReflectedForJson $reflected;

  private static int $depth = 0;
  private const MAX_DEPTH = 64;

  public function __construct(object $instance, string $class_name, string $json_encoder) {
    $this->json_encoder = $json_encoder;
    $this->instance = $instance;
    $this->reflected = InstanceReflectedForJson::getCachedOrCreate($class_name, $json_encoder);
  }

  /**
   * @throws KphpJsonEncodeException
   */
  public function serializeInstanceToJson(int $flags, array $more): string {
    self::$depth = 0;
    $writer = new JsonWriter($flags & JSON_PRETTY_PRINT, $flags & JSON_PRESERVE_ZERO_FRACTION);
    $this->encodeCurrentInstance($writer, $more);

    if (!$writer->is_complete()) {
      throw new KphpJsonEncodeException("internal error: resulted in incomplete json");
    }
    return $writer->get_final_json();
  }

  private function encodeCurrentInstance(JsonWriter $writer, array $more = []) {
    if ($this->reflected->flatten_class) {
      $this->encodeFlattenClassSingleField($writer);
      return;
    }

    if (++self::$depth > self::MAX_DEPTH) {
      throw new KphpJsonEncodeException("allowed depth=" . self::MAX_DEPTH . " of json object exceeded");
    }
    $writer->start_object();

    if (!$this->reflected->parent) {
      $this->encodeRegularClassFields($writer);
    } else {
      // in final json we want parent fields to appear first
      /** @var InstanceReflectedForJson[] $parents */
      $parents = [];
      for ($parent = $this->reflected; $parent; $parent = $parent->parent) {
        array_unshift($parents, $parent);
      }
      foreach ($parents as $parent) {
        $p_serializer = new JsonSerializer($this->instance, $parent->class_name, $this->json_encoder);
        $p_serializer->encodeRegularClassFields($writer);
      }
    }

    foreach ($more as $k => $v) {
      $writer->write_key($k);
      $this->encodeValue($writer, $v, 0, false, false);
    }
    $writer->end_object();
    --self::$depth;
  }

  private function encodeFlattenClassSingleField(JsonWriter $writer) {
    $field = array_first_value($this->reflected->fields);
    $v = $this->reflected->getProperty($field->field_name)->getValue($this->instance);
    $this->encodeValue($writer, $v, $field->float_precision, $field->array_as_hashmap, $field->raw_string);
  }

  private function encodeRegularClassFields(JsonWriter $writer) {
    // if @kphp-json 'fields' exists above a class, we use it to output fields in that order
    if ($this->reflected->kphp_json_tags) {
      $tag_fields = $this->reflected->kphp_json_tags->find_tag(function(KphpJsonTag $tag) {
        return $tag->attr_type == JsonAttrType::fields && (!$tag->for_encoder || $tag->for_encoder === $this->json_encoder);
      });
      if ($tag_fields) {
        foreach (explode(',', trim($tag_fields->value, ',')) as $field_name) {
          $this->encodeKeyAndFieldUnlessSkipped($writer, $this->reflected->fields[$field_name]);
        }
        return;
      }
    }
    // otherwise, we output fields in an order they are declared
    foreach ($this->reflected->fields as $field) {
      $this->encodeKeyAndFieldUnlessSkipped($writer, $field);
    }
  }

  private function encodeKeyAndFieldUnlessSkipped(JsonWriter $writer, FieldJsonSettings $field) {
    if ($field->skip_when_encoding) {
      return;
    }

    $property = $this->reflected->getProperty($field->field_name);
    $v = $property->isInitialized($this->instance) ? $property->getValue($this->instance) : null;
    if ($v === null && !$field->type->isNullAllowed()) {
      trigger_error("field {$this->reflected->class_name}::\$$field->field_name seems to be uninitialized", E_USER_WARNING);
      throw new KphpJsonEncodeException("field {$this->reflected->class_name}::\$$field->field_name seems to be uninitialized");
    }

    if ($field->skip_if_default && $v === $this->getPropertyDefaultValue($property)) {
      return;
    }

    $writer->write_key($field->json_key);
    $this->encodeValue($writer, $v, $field->float_precision, $field->array_as_hashmap, $field->raw_string);
  }

  private function encodeValue(JsonWriter $writer, $v, int $float_precision, bool $array_as_hashmap, bool $raw_string) {
    if ($raw_string) {
      $writer->write_raw_string($v);
      return;
    }
    if ($float_precision !== 0) {
      $writer->set_float_precision($float_precision);
    }
    $this->writeAnyValue($writer, $v, $array_as_hashmap);
    if ($float_precision !== 0) {
      $writer->restore_float_precision();
    }
  }

  private function getPropertyDefaultValue(\ReflectionProperty $property) {
    $properties = $property->getDeclaringClass()->getDefaultProperties();
    return $property->isInitialized($this->instance) ? ($properties[$property->name] ?? null) : null;
  }

  private function writeAnyValue(JsonWriter $writer, $v, bool $array_as_hashmap) {
    if (is_int($v)) {
      $writer->write_int($v);
    } else if (is_string($v)) {
      $writer->write_string($v);
    } else if (is_float($v)) {
      $writer->write_double($v);
    } else if (is_bool($v)) {
      $writer->write_bool($v);
    } else if (is_null($v)) {
      $writer->write_null();

    } else if (is_array($v)) {
      $as_vector = !$array_as_hashmap && JsonUtils::is_array_vector_or_pseudo_vector($v);
      if ($as_vector) {
        $writer->start_array();
        foreach ($v as $item) {
          $this->writeAnyValue($writer, $item, false);
        }
        $writer->end_array();
      } else {
        $writer->start_object();
        foreach ($v as $k => $item) {
          $writer->write_key($k, true);
          $this->writeAnyValue($writer, $item, false);
        }
        $writer->end_object();
      }

    } else if (is_object($v)) {
      $sub_serializer = new JsonSerializer($v, get_class($v), $this->json_encoder);
      $sub_serializer->encodeCurrentInstance($writer);

    } else {
      throw new KphpJsonEncodeException("unexpected runtime value " . gettype($v));
    }
  }
}
