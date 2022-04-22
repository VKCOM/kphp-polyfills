<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

namespace KPHP\JsonSerialization;

function is_uppercase(string $str): bool {
  return (bool)preg_match('/^[A-Z]+$/', $str);
}

// perform "blahBlah" to "blah_blah" translation
function transform_to_snake_case(string $origin) : string {
  $name = "";

  foreach (str_split($origin) as $c) {
    if (is_uppercase($c)) {
      if (strlen($name) > 0 && $name[-1] != '_') {
        $name .= '_';
      }
    }
    $name .= strtolower($c);
  }
  return $name;
}

// perform "blah_blah" to "blahBlah" translation
function transform_to_camel_case(string $origin) : string {
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
