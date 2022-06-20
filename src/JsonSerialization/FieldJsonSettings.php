<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

// represents final settings for a field
// after merging its @kphp-json tags with @kphp-json of a class and encoder constants at exact call
// similar to FieldJsonSettings in KPHP
use KPHP\PhpDocParsing\PhpDocType;

class FieldJsonSettings {
  public string $json_key;
  public bool $skip_when_encoding = false;
  public bool $skip_when_decoding = false;
  public bool $skip_if_default = false;
  public bool $array_as_hashmap = false;
  public bool $required = false;
  public bool $raw_string = false;
  public int $float_precision = 0;

  // these fields are absent in KPHP, they are to make polyfills work
  public string $field_name;
  public \KPHP\PhpDocParsing\PhpDocType $type;


  // similar to merge_and_inherit_json_tags() in KPHP
  public static function merge_and_inherit_json_tags(?KphpJsonTagList $field_json_tags, ?KphpJsonTagList $class_json_tags, string $json_encoder, \ReflectionProperty $property, PhpDocType $field_type): FieldJsonSettings {
    $s = new FieldJsonSettings();
    $s->field_name = $property->name;
    $s->type = $field_type;

    // for 'public int $id;' — no default and non-nullable type — set 'required' so that decode() would fire unless exists
    // it could be overridden with `@kphp-json required = false`
    if (JsonUtils::property_get_default_value($property) === null && !$field_type->isNullAllowed()) {
      $s->required = true;
    }

    // loop over constants in encoder that can be overridden by a class
    foreach ([
               JsonAttrType::rename_policy,
               JsonAttrType::visibility_policy,
               JsonAttrType::skip_if_default,
               JsonAttrType::float_precision,
             ] as $encoder_const) {
      /** @var ?KphpJsonTag $override_klass_tag */
      $override_klass_tag = null;
      if ($class_json_tags) {
        foreach ($class_json_tags->tags as $klass_tag) {
          if ($klass_tag->for_encoder && $klass_tag->for_encoder !== $json_encoder) {
            continue;
          }
          if ($klass_tag->attr_type === $encoder_const) {
            // here and below, we use the fact that no-'for' tag appears above 'for', so just find the last one satisfying
            $override_klass_tag = $klass_tag;
          }
        }
      }
      $value = $override_klass_tag ? $override_klass_tag->value : constant($json_encoder . '::' . $encoder_const);

      switch ($encoder_const) {
        case JsonAttrType::rename_policy:
          $s->json_key = JsonUtils::transform_json_name_to($value, $property->name);
          break;
        case JsonAttrType::skip_if_default:
          $s->skip_if_default = $value;
          break;
        case JsonAttrType::float_precision:
          $s->float_precision = $value;
          break;
        case JsonAttrType::visibility_policy:
          $skip_unless_changed_below = $value === 'all' ? false : !$property->isPublic();
          $s->skip_when_encoding = $skip_unless_changed_below;
          $s->skip_when_decoding = $skip_unless_changed_below;
          break;
        default:
          assert(0 && "unexpected json attr_type in class/encoder");
      }
    }

    // loop over attrs that can be only above class
    if ($class_json_tags) {
      foreach ($class_json_tags->tags as $klass_tag) {
        if ($klass_tag->for_encoder && $klass_tag->for_encoder !== $json_encoder) {
          continue;
        }

        switch ($klass_tag->attr_type) {
          case JsonAttrType::flatten:
            $s->skip_if_default = false;
            $s->skip_when_encoding = false;
            $s->skip_when_decoding = false;
            break;
          case JsonAttrType::fields:
            $skip_unless_changed_below = strpos($klass_tag->value, "," . $property->name . ",") === false;
            $s->skip_when_encoding = $skip_unless_changed_below;
            $s->skip_when_decoding = $skip_unless_changed_below;
            break;
        }
      }
    }

    // loop over attrs above field: they override class/encoder
    if ($field_json_tags) {
      foreach ($field_json_tags->tags as $tag) {
        if ($tag->for_encoder && $tag->for_encoder !== $json_encoder) {
          continue;
        }

        switch ($tag->attr_type) {
          case JsonAttrType::rename:
            $s->json_key = $tag->value;
            break;
          case JsonAttrType::skip:
            $s->skip_when_decoding = $tag->value === 'decode' || $tag->value === 'true';
            $s->skip_when_encoding = $tag->value === 'encode' || $tag->value === 'true';
            break;
          case JsonAttrType::array_as_hashmap:
            $s->array_as_hashmap = $tag->value;
            break;
          case JsonAttrType::raw_string:
            $s->raw_string = $tag->value;
            break;
          case JsonAttrType::required:
            $s->required = $tag->value;
            break;
          case JsonAttrType::float_precision:
            $s->float_precision = $tag->value;
            break;
          case JsonAttrType::skip_if_default:
            $s->skip_if_default = $tag->value;
            break;
          default:
            assert(0 && "unexpected json attr_type in field codegen");
        }
      }
    }

    return $s;
  }
}
