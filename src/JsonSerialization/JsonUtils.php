<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2022 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

namespace KPHP\JsonSerialization;


// perform "blahBlah" to "blah_blah" translation
function transform_to_snake_case(string $origin): string {
  $name = "";

  foreach (str_split($origin) as $c) {
    if (ctype_upper($c)) {
      if (strlen($name) > 0 && $name[-1] != '_') {
        $name .= '_';
      }
    }
    $name .= strtolower($c);
  }
  return $name;
}

// perform "blah_blah" to "blahBlah" translation
function transform_to_camel_case(string $origin): string {
  $name = "";

  $i = 0;
  if ($i < strlen($origin) && $origin[$i] == '_') {
    $name .= '_';
    ++$i;
  }

  for (; $i < strlen($origin); ++$i) {
    $cur = $origin[$i];
    $has_next = $i + 1 < strlen($origin);
    if ($cur == '_' && $has_next) {
      $next = $origin[$i + 1];
      $name .= strtoupper($next);
      ++$i;
    } else {
      $name .= $cur;
    }
  }

  return $name;
}

class JsonUtils {
  static function property_get_default_value(\ReflectionProperty $field) {
    $klass_defs = $field->getDeclaringClass()->getDefaultProperties();
    return $klass_defs[$field->name] ?? null;
  }

  static function transform_json_name_to(string $policy, string $name): string {
    switch ($policy) {
      case 'snake_case':
        return transform_to_snake_case($name);
      case 'camelCase':
        return transform_to_camel_case($name);
      default:
        return $name;
    }
  }

  static function is_array_vector_or_pseudo_vector(array $arr): bool {
    $last = count($arr) - 1;
    if ($last === -1) {
      return true;
    }

    return array_key_exists(0, $arr) && array_key_exists($last, $arr);
  }
}
