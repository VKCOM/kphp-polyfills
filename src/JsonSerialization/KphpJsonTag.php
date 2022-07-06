<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

// one `@kphp-json attr=value` is represented as this class
// there could be for statement: `@kphp-json for MyEncoder attr=value`
// similar to KphpJsonTag in KPHP
class KphpJsonTag {
  /** @var string one of constants in JsonAttrType */
  public string $attr_type;

  /** @var ?string if 'for' specified, it contains fqn */
  public ?string $for_encoder;

  /** @var bool|int|string depends on $type */
  public $value;

  // similar to parse_kphp_json_tag() in KPHP
  /** @noinspection PhpDuplicateSwitchCaseBodyInspection */
  public static function parse_from_doc_tag(\KPHP\PhpDocParsing\UseResolver $use_resolver, string $str): KphpJsonTag {
    $for_encoder = substr($str, 0, 4) === 'for ' ? self::extract_for_encoder_from_kphp_json_tag($use_resolver, $str) : null;

    $pos_eq = strpos($str, '=');
    $attr_type = $pos_eq === false ? $str : trim(substr($str, 0, $pos_eq));
    $rhs = $pos_eq === false ? "" : trim(substr($str, $pos_eq + 1));

    $json_tag = new KphpJsonTag();
    $json_tag->attr_type = $attr_type;
    $json_tag->for_encoder = $for_encoder;

    switch ($attr_type) {
      case JsonAttrType::rename:
        if ($rhs === '') {
          throw new KphpJsonParsingException("@kphp-json '$attr_type' expected to have a value after '='");
        }
        $json_tag->value = self::parse_rename($attr_type, $rhs);
        break;
      case JsonAttrType::skip:
        $json_tag->value = self::parse_skip($attr_type, $rhs);
        break;
      case JsonAttrType::array_as_hashmap:
        $json_tag->value = self::parse_bool_or_true_if_nothing($attr_type, $rhs);
        break;
      case JsonAttrType::raw_string:
        $json_tag->value = self::parse_bool_or_true_if_nothing($attr_type, $rhs);
        break;
      case JsonAttrType::required:
        $json_tag->value = self::parse_bool_or_true_if_nothing($attr_type, $rhs);
        break;
      case JsonAttrType::float_precision:
        if ($rhs === '') {
          throw new KphpJsonParsingException("@kphp-json '$attr_type' expected to have a value after '='");
        }
        $json_tag->value = self::parse_float_precision($attr_type, $rhs);
        break;
      case JsonAttrType::skip_if_default:
        $json_tag->value = self::parse_bool_or_true_if_nothing($attr_type, $rhs);
        break;
      case JsonAttrType::visibility_policy:
        if ($rhs === '') {
          throw new KphpJsonParsingException("@kphp-json '$attr_type' expected to have a value after '='");
        }
        $json_tag->value = self::parse_visibility_policy($attr_type, $rhs);
        break;
      case JsonAttrType::rename_policy:
        if ($rhs === '') {
          throw new KphpJsonParsingException("@kphp-json '$attr_type' expected to have a value after '='");
        }
        $json_tag->value = self::parse_rename_policy($attr_type, $rhs);
        break;
      case JsonAttrType::flatten:
        $json_tag->value = self::parse_bool_or_true_if_nothing($attr_type, $rhs);
        break;
      case JsonAttrType::fields:
        if ($rhs === '') {
          throw new KphpJsonParsingException("@kphp-json '$attr_type' expected to have a value after '='");
        }
        $json_tag->value = self::parse_fields_delimited_by_comma($attr_type, $rhs);
        break;
      default:
        throw new KphpJsonParsingException("Unknown @kphp-json '$attr_type'");
    }

    return $json_tag;
  }

// @kphp-json can be prefixed with 'for': `@kphp-json for MyJsonEncoder attr = value`
// here we extract this MyJsonEncoder, resolving uses and leaving a pointer after it into str
  private static function extract_for_encoder_from_kphp_json_tag(\KPHP\PhpDocParsing\UseResolver $use_resolver, string &$str): string {
    $str = trim(substr($str, 4));
    $pos_sp = strpos($str, ' ');
    if ($pos_sp === false) {
      throw new KphpJsonParsingException("Nothing after @kphp-json for");
    }

    $for_encoder = $use_resolver->resolveName(substr($str, 0, $pos_sp));
    if (!class_exists($for_encoder)) {
      throw new KphpJsonParsingException("Class $for_encoder not found after @kphp-json for");
    }
    $for_encoder = trim($for_encoder, '\\'); // trim leading \\, as ::class ($json_encoder in runtime) is without

    $str = trim(substr($str, $pos_sp));
    return $for_encoder;
  }

  static private function parse_rename(string $attr_type, string $rhs): string {
    return $rhs;
  }

  static private function parse_fields_delimited_by_comma(string $attr_type, string $rhs): string {
    // convert "$id, $age" to ",id,age," for fast later search of substr ",{name},"
    $fields_delim = ',';
    foreach (preg_split('/[\$, ]+/', $rhs) as $field_name) {
      $fields_delim .= $field_name;
      $fields_delim .= ',';
    }
    return $fields_delim;
  }

  static private function parse_bool_or_true_if_nothing(string $attr_type, string $rhs): bool {
    if ($rhs === '' || $rhs === 'true' || $rhs === '1') {
      return true;
    }
    if ($rhs !== 'false' && $rhs !== '0') {
      throw new KphpJsonParsingException("@kphp-json '$attr_type' should be empty or true|false, got '$rhs'");
    }
    return false;
  }

  static private function parse_float_precision(string $attr_type, string $rhs): int {
    if (!is_numeric($rhs) || (int)$rhs < 0) {
      throw new KphpJsonParsingException("@kphp-json '$attr_type' value should be non negative integer, got '$rhs'");
    }
    return (int)$rhs;
  }

  static private function parse_skip(string $attr_type, string $rhs): string {
    if ($rhs === '' || $rhs === '1') {
      $rhs = 'true';
    }
    if ($rhs === '0') {
      $rhs = 'false';
    }
    if ($rhs !== 'true' && $rhs !== 'false' && $rhs !== 'encode' && $rhs !== 'decode') {
      throw new KphpJsonParsingException("@kphp-json '$attr_type' should be true|false|encode|decode, got '$rhs'");
    }
    return $rhs;
  }

  static private function parse_visibility_policy(string $attr_type, string $rhs): string {
    if ($rhs !== 'all' && $rhs !== 'public') {
      throw new KphpJsonParsingException("@kphp-json '$attr_type' should be all|public, got '$rhs'");
    }
    return $rhs;
  }

  static private function parse_rename_policy(string $attr_type, string $rhs): string {
    if ($rhs !== 'snake_case' && $rhs !== 'camelCase' && $rhs !== 'none') {
      throw new KphpJsonParsingException("@kphp-json '$attr_type' should be none|snake_case|camelCase, got '$rhs'");
    }
    return $rhs;
  }
}
