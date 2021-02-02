<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection PhpUnused */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpComposerExtensionStubsInspection */
/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpDocMissingReturnTagInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection KphpDocInspection */
/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

/*
 * This file contains implemetation of KPHP native functions written in plain PHP.
 * So, while executing a script in plain PHP - this implementation is used,
 * but after compilation they are replaced by built-in ones.
 *
 * (Some KPHP functions can't be expressed in PHP - they are written with C, see vkext.helper.php)
 */

#ifndef KPHP  // all contents of this file is invisible for KPHP


#region types

// functions that are KPHP keywords, related to type system


/**
 * tuple(T1, T2, ...) - compile-time sized, read-only "array" with compile-time known indexing access
 * In plain PHP it is just an array of input arguments: tuple(1,'s') === [1,'s']
 * In KPHP types are tracked separately
 * @noinspection PhpDocSignatureInspection
 * @noinspection PhpUnusedParameterInspection
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function tuple(...$args) {
  // turn off PhpStorm native inferring
  return ${'args'};
}

/**
 * shape(['k1' => T1, 'k2' => T2, ...]) - like tuple, but with named arguments
 * In plain PHP it is just an associative array, the same as provided argument
 * In KPHP types are tracked separately, there is no hashtable (and even strings) at runtime
 * @noinspection PhpDocSignatureInspection
 * @noinspection PhpUnusedParameterInspection
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function shape(array $associative_arr) {
  // turn off PhpStorm native inferring
  return ${'associative_arr'};
}

/**
 * not_null(?T) : T
 * @param any $any_value
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function not_null($any_value) {
  if ($any_value === null) {
    warning("Passed 'null' to not_null() in PHP");
  }
  return $any_value;
}

/**
 * not_false(T|false) : T
 * @param any $any_value
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function not_false($any_value) {
  if ($any_value === false) {
    warning("Passed 'false' to not_false() in PHP");
  }
  return $any_value;
}


#endregion


#region instance cache

// in KPHP, instances are cached for $ttl across requests in shared memory
// in PHP, just use globals for current request - not effective, but polyfill logic remains the same

global $kphp_fake_instance_cache;
$kphp_fake_instance_cache = [];


/**
 * instance_cache_fetch(SomeClass::class, 'key') : SomeClass
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function instance_cache_fetch(string $type, string $key) {
  global $kphp_fake_instance_cache;
  if (isset($kphp_fake_instance_cache[$key]) && $kphp_fake_instance_cache[$key] instanceof $type) {
    return $kphp_fake_instance_cache[$key];
  }
  return null;
}

/**
 * @param object $value Any instance
 * @param int    $ttl In seconds (ignored in php)
 */
function instance_cache_store(string $key, $value, $ttl = -1): bool {
  global $kphp_fake_instance_cache;
  $kphp_fake_instance_cache[$key] = clone $value;
  return true;
}

function instance_cache_update_ttl(string $key, int $ttl = 0): bool {
  // no ttl in php polyfill
  global $kphp_fake_instance_cache;
  return isset($kphp_fake_instance_cache[$key]);
}

function instance_cache_delete(string $key): bool {
  global $kphp_fake_instance_cache;
  $deleted = isset($kphp_fake_instance_cache[$key]);
  unset($kphp_fake_instance_cache[$key]);
  return $deleted;
}


#endregion


#region instances

// casting instances to different types


/**
 * instance_cast($any_object, SomeClass::class) : SomeClass
 * @param object $instance Any non-null instance - known to be of type SomeClass, but can't be inferred
 * @param string $class_name Compile-time constant
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function instance_cast($instance, string $class_name) {
  // it just returns the argument, but KPHP infers it as SomeClass
  return $instance;
}

/**
 * instance_to_array($any_object) : var[] - deep convertation to object to array
 * Similar to (array)$any_object, but deep and with strict behaviour.
 * Useful for logging and debugging.
 * For all classes that are array-converted, KPHP generates an effective C++ visitor.
 * @param object $instance Any instance
 * @param bool   $with_class_names Should the resulting array contain class names
 * @return mixed[]
 */
function instance_to_array($instance, $with_class_names = false) {
  // (array) $instance in PHP outputs private/protected fields as '\0ClassName\0fieldName'
  // kphp implementation doesn't depend on access type, so demangle such array keys to just 'fieldName'
  $demangleField = function($key) {
    if ($key[0] === "\0") {
      $key = preg_replace("/\\0.+\\0/", '', $key);
    }
    return $key;
  };

  $toArray = function($v) use (&$toArray, &$demangleField, &$with_class_names) {
    if (is_object($v)) {
      $result = [];
      foreach ((array)$v as $field => $value) {
        $result[$demangleField($field)] = $toArray($value);
      }
      if ($with_class_names) {
        $result['__class_name'] = get_class($v);
      }
      return $result;
    }
    if (is_array($v)) {
      return array_map($toArray, $v);
    }
    return $v;
  };

  assert(is_object($instance));
  return $toArray($instance);
}


#endregion


#region resumable

// resumable functions - forks - are close to coroutines (green threads); in PHP they remain synchronous


global $__forked;
$__forked = [];

/**
 * fork(f(...$args)) : future<ReturnT(f)>  (int in PHP)
 * @param any $x
 */
function fork($x) {
  global $__forked;

  $__forked[] = $x;
  return count($__forked);
}

function _php_wait_helper($id) {
  global $__forked;
  $cnt = is_array($__forked) ? count($__forked) : 0;
  return 0 < $id && $id <= $cnt && $__forked[$id - 1] !== '__already_gotten__';
}

function wait($id) {
  global $__forked;

  if (!_php_wait_helper($id)) {
    return null;
  }

  $result = $__forked[$id - 1];

  $__forked[$id - 1] = '__already_gotten__';

  return $result;
}

function wait_multi($futures) {
  $result = [];
  foreach ($futures as $key => $future) {
    $result[$key] = wait($future);
  }
  return $result;
}

function wait_synchronously($id) {
  return wait($id);
}

function wait_concurrently($id) {
  static $waiting = [];

  if (!$waiting[$id]) {
    $waiting[$id] = true;
    _php_wait_helper($id);
    unset($waiting[$id]);
  } else {
    while ($waiting[$id]) {
      sched_yield();
      if ($waiting[$id]) {
        usleep(10 * 1000);
      }
    }
  }
}

function wait_queue_create(array $futures): array {
  return $futures;
}

function wait_queue_push(&$future_queue, $future) {
  assert(is_array($future_queue));    // array in php, future_queue<T> in kphp
  assert($future >= 0);      // int in php, future<T> in kphp

  $future_queue[] = $future;
}

function wait_queue_empty($future_queue) {
  assert(is_array($future_queue));

  return count($future_queue) === 0;
}

function wait_queue_next(&$future_queue, $timeout = -1.0) {
  if (wait_queue_empty($future_queue)) {
    return 0;
  }
  return array_shift($future_queue);
}

function wait_queue_next_synchronously(&$future_queue) {
  return wait_queue_next($future_queue, -1);
}

function sched_yield() {
}

/**
 * @param float $timeout in seconds
 */
function sched_yield_sleep($timeout) {
}

function rpc_tl_query_result_synchronously($query_ids) {
  return rpc_tl_query_result($query_ids);
}

function rpc_get_synchronously($qid) {
  return rpc_get($qid);
}

function get_running_fork_id(): int {
  return 0;
}

function set_wait_all_forks_on_finish(bool $wait = true): bool {
  static $wait_all_forks = false;
  $prev = $wait_all_forks;
  $wait_all_forks = $wait;
  return $prev;
}


#endregion


#region arrays

// array_* functions that are missing in PHP or made for better type inferring


/**
 * @return int|string|null
 */
function array_first_key(array $a) {
  reset($a);
  return key($a);
}

/**
 * array_first_value(T[]) : T
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function array_first_value(array $a) {
  reset($a);
  return current($a);
}

/**
 * @return int|string|null
 */
function array_last_key(array $a) {
  end($a);
  return key($a);
}

/**
 * array_last_value(T[]) : T
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function array_last_value(array $a) {
  end($a);
  return current($a);
}

/**
 * Swap two items in an array.
 * Done efficiently in KPHP, especially for vectors.
 */
function array_swap_int_keys(array &$a, int $idx1, int $idx2): void {
  if ($idx1 != $idx2 && isset($a[$idx1]) && isset($a[$idx2])) {
    $tmp = $a[$idx1];
    $a[$idx1] = $a[$idx2];
    $a[$idx2] = $tmp;
  }
}

/**
 * @return string[]
 */
function array_keys_as_strings(array $a) {
  $keys = [];
  foreach ($a as $key => $_) {
    $keys[] = (string)$key;
  }
  return $keys;
}

/**
 * @return int[]
 */
function array_keys_as_ints(array $a) {
  $keys = [];
  foreach ($a as $key => $_) {
    $keys[] = (int)$key;
  }
  return $keys;
}

/**
 */
function array_merge_into(array &$a, array $another_array) {
  $a = array_merge($a, $another_array);
}

/**
 * array_find(T[], fn) : tuple(key, T)
 * @return tuple(int|string|null, any)
 */
function array_find(array $ar, callable $clbk) {
  foreach ($ar as $k => $v) {
    if ($clbk($v)) {
      return tuple($k, $v);
    }
  }
  return tuple(null, null);
}

/**
 * array_filter_by_key(T[], fn) : T[]
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function array_filter_by_key(array $array, callable $callback) {
  return array_filter($array, $callback, ARRAY_FILTER_USE_KEY);
}

/**
 * A useful function to reserve array memory - if you know in advance, how much elements will be inserted to it.
 * Effectice especially for vectors, as there will be no reallocations on insertion.
 *
 * @param array $arr          Target array (vector/map)
 * @param int   $int_keys_num Amount of int keys
 * @param int   $str_keys_num Amount of string keys
 * @param bool  $is_vector    Should it be a vector (if string keys amount is 0)
 */
function array_reserve(&$arr, $int_keys_num, $str_keys_num, $is_vector) {
  // in PHP does nothing
}

/**
 * More useful @see array_reserve() alias for map with string keys
 *
 * @param array $arr          Target array (vector/map)
 * @param int   $capacity     Amount of the elements (for vector) or int keys (for map with int keys)
 */
function array_reserve_vector(&$arr, $capacity) {
    // in PHP does nothing
}

/**
 * More useful @see array_reserve() alias for map with int keys
 *
 * @param array $arr          Target array (vector/map)
 * @param int   $int_keys_num Amount of int keys
 */
function array_reserve_map_int_keys(&$arr, $int_keys_num) {
    // in PHP does nothing
}

/**
 * More useful @see array_reserve() alias for map with string keys
 *
 * @param array $arr          Target array (vector/map)
 * @param int   $str_keys_num Amoutn of string keys
 */
function array_reserve_map_string_keys(&$arr, $str_keys_num) {
    // in PHP does nothing
}

/**
 * The same as @see array_reserve(), but takes all sizes (length, key type, is vector) from array $base.
 *
 * @param array $arr  Target array (vector/map)
 * @param array $base Source of array size
 */
function array_reserve_from(array &$arr, array $base) {
  // in PHP does nothing
}

/**
 * @deprecated
 * @param array $a
 * @param int $n
 * @return array [key, value]
 */
function _php_getElementByPos_helper($a, $n) {
  // return is_array($a) ? each(array_slice($a, $n, 1)) : array();

  if (!is_array($a)) {
    return [];
  }
  $l = count($a);

  if ($n < -$l / 2) {
    $n += $l;
    if ($n < 0) {
      return [];
    }
  }

  if ($n > $l / 2) {
    $n -= $l;
    if ($n >= 0) {
      return [];
    }
  }

  if ($n < 0) {
    end($a);
    $n++;
    while ($n < 0) {
      $n++;
      prev($a);
    }
  } else {
    reset($a);
    while ($n > 0) {
      $n--;
      next($a);
    }
  }

  // replacement of deprecated each() function
  $result = [
    1       => current($a),
    'value' => current($a),
    0       => key($a),
    'key'   => key($a),
  ];
  next($a);

  return $result;
}

/**
 * @deprecated
 * @see array_first_key(), array_last_key()
 * @param array $a
 * @param int   $n
 * @return any
 */
function getKeyByPos($a, $n) {
  $element = _php_getElementByPos_helper($a, $n);
  return $element[0];
}

/**
 * @deprecated
 * @see array_first_value(), array_last_value()
 * @param array $a
 * @param int   $n
 * @return any
 */
function getValueByPos($a, $n) {
  $element = _php_getElementByPos_helper($a, $n);
  return $element[1];
}

/**
 * create_vector($n, $x) : typeof($x)[] (vector-array of size n)
 * @param int $n
 * @param any $x
 * @return array
 */
function create_vector($n, $x) {
  $res = [];
  for ($i = 0; $i < $n; $i++) {
    $res[] = $x;
  }
  return $res;
}


#endregion


#region serialization

// serialize instances and vars to binary msgpack representation (and back)


/** @return any */
function _php_serialize_helper_run_or_warning(callable $fun) {
  try {
    return $fun();
  } catch (Throwable $e) {
    warning($e->getMessage() . "\n" . $e->getTraceAsString());
    return null;
  }
}

function instance_serialize(object $instance): ?string {
  KPHP\InstanceSerialization\ClassTransformer::$depth = 0;
  return _php_serialize_helper_run_or_warning(static function() use ($instance) {
    $packer = (new MessagePack\Packer(MessagePack\PackOptions::FORCE_STR))->extendWith(new KPHP\InstanceSerialization\ClassTransformer());
    return $packer->pack($instance);
  });
}

function instance_deserialize(string $packed_str, string $type_of_instance): ?object {
  return _php_serialize_helper_run_or_warning(static function() use ($packed_str, $type_of_instance) {
    $unpacked_array = msgpack_deserialize_safe($packed_str);

    $instance_parser = new KPHP\InstanceSerialization\InstanceParser($type_of_instance);
    return $instance_parser->fromUnpackedArray($unpacked_array);
  });
}

/**
 * @param mixed $value
 */
function msgpack_serialize($value): string {
  return _php_serialize_helper_run_or_warning(static function() use ($value) {
    return msgpack_serialize_safe($value);
  });
}

/**
 * @param mixed $value
 * @throws MessagePack\Exception\InvalidOptionException
 * @throws MessagePack\Exception\PackingFailedException
 */
function msgpack_serialize_safe($value): string {
  $packer = new MessagePack\Packer(MessagePack\PackOptions::FORCE_STR);
  return $packer->pack($value);
}

/**
 * @param string $packed_str
 * @return mixed
 */
function msgpack_deserialize(string $packed_str) {
  return _php_serialize_helper_run_or_warning(static function() use ($packed_str) {
    return msgpack_deserialize_safe($packed_str);
  });
}

/**
 * @throws MessagePack\Exception\InvalidOptionException
 * @throws MessagePack\Exception\UnpackingFailedException
 * @return any
 */
function msgpack_deserialize_safe(string $packed_str) {
  $unpacker = new MessagePack\BufferUnpacker($packed_str, null);
  $result = $unpacker->unpack();
  if (($remaining = $unpacker->getRemainingCount())) {
    $off = strlen($packed_str) - $remaining;
    throw new MessagePack\Exception\UnpackingFailedException("Consumed only first {$off} characters of " . strlen($packed_str) . " during deserialization");
  }
  return $result;
}


#endregion


#region confdata

// confdata functions can't be polyfilled and therefore must be called only if kphp === 1


function is_confdata_loaded(): bool {
  return false;
}

/** @return mixed */
function confdata_get_value(string $key) {
  return false;
}

/** @return mixed[] */
function confdata_get_values_by_predefined_wildcard(string $prefix) {
  return [];
}


#endregion


#region profiler

// profiler is enabled only in KPHP with env KPHP_PROFILER=1, PHP can't polyfill this


/**
 * Is KPHP profiler enabled.
 * When disabled, invocations are replaced with 'false' while compilation,
 * that's whe if(profiler_is_enabled()) are cut our by gcc, and you can use it even in highload places
 */
function profiler_is_enabled(): bool {
  return false;
}

/**
 * Tells KPHP profiler to "split" current function.
 * As a purpose, instead of just rpcCall() we want to see rpcCall(mc), rpcCall(targ2) and others for engine targets.
 * In this case, $label is an actor name.
 */
function profiler_set_function_label(string $label) {
}

/**
 * Not obligatory function for KPHP profiler, forcing a given suffix to a profile output filename.
 * (by default suffix is a name of @kphp-profile function)
 * Ideological usage pattern: call it in special cases somewhere deeply in callstack - to find a wanted file easier.
 */
function profiler_set_log_suffix(string $suffix) {
}


#endregion


#region memory

// getting memory stats from PHP code at production, don't confuse with deep-memory profiling


/**
 * An array ["$var_name" => size_in_bytes].
 * Works only in KPHP, don't use it in production - only to debug, which globals/statics allocate huge pieces of memory.
 *
 * While compiling, a special env variable should be set to enable this functions:
 * KPHP_ENABLE_GLOBAL_VARS_MEMORY_STATS=1 kphp ...
 *
 * In PHP, does nothing.
 * @return int[]
 */
function get_global_vars_memory_stats() {
  return [];
}

/**
 * Returns currently used and dirty memory (in bytes)
 */
function memory_get_total_usage(): int {
  return 0;
}

/**
 * Returns heap memory usage (system heap, not script allocator) (in bytes)
 */
function memory_get_static_usage(): int {
  return 0;
}


#endregion


#region misc


/**
 * Prints warning to strerr
 * @param string $str
 */
function warning($str) {
  trigger_error($str, E_USER_WARNING);
}

/**
 * Produces an user error and interrupts the script execution
 */
function critical_error(string $message) {
  trigger_error($message, E_USER_ERROR);
}

/**
 * Useful for dev purposes: this callback is invoked when a runtime warning occurs.
 * It can be shown on screen for developer, for example.
 * Do not use it in production! Use json log analyzer and trace C++->PHP mapper instead.
 * @param callable(string, string[]) $callback
 *  $message: warning text
 *  $stacktrace: function names demangled from cpp trace, even without debug symbols - but slow, only for dev
 */
function register_kphp_on_warning_callback(callable $callback) {
  $handler = function($errno, $message) use ($callback) {
    $stacktrace = array_map(function($o) { return $o['function']; }, debug_backtrace());
    $callback($message, $stacktrace);
  };
  set_error_handler($handler);
}

/**
 * Like register_kphp_on_warning_callback(), but it is not linked to any runtime error:
 * instead, it allows to get current demangled backtrace at the execution point.
 * Note! Demangling works slowly, don't use it in highload places!
 */
function kphp_backtrace(bool $pretty = true): array {
  $backtrace = array_column(debug_backtrace(), "function");
  array_shift($backtrace);
  return $backtrace;
}

/**
 * Defines a context for runtime warnings (to be written to json error logs).
 * @param mixed[] $tags key-value tags (treated like an aggregate)
 * @param mixed[] $extra_info key-value extra arbitrary data (not an aggregator)
 * @param string $env environment (e.g.: staging / production)
 */
function kphp_set_context_on_error(array $tags, array $extra_info, string $env = '') {
}


/**
 * 'libs' are ability to split a monorepo into several repos, but with linkage to one whole KPHP binary.
 * require_lib() just includes PHP file here, but is a keyword in KPHP that manages dependencies.
 * @param string $lib_name A compile-time constant
 */
function require_lib(string $lib_name) {
  if (!defined('EXTERNAL_KPHP_LIBS_POLYFILLS_ROOT')) {
    warning('EXTERNAL_KPHP_LIBS_POLYFILLS_ROOT not defined (should be a path string)');
    return;
  }

  if (defined('EXTERNAL_KPHP_LIBS_DEBUG') && EXTERNAL_KPHP_LIBS_DEBUG) {
    if (!defined('EXTERNAL_KPHP_LIBS_DEBUG_ROOT')) {
      warning('EXTERNAL_KPHP_LIBS_DEBUG_ROOT not defined (should be a path string with {user} placeholder)');
      return;
    }
    $MY_LIBS_ROOT = str_replace('{user}', exec('whoami'), EXTERNAL_KPHP_LIBS_DEBUG_ROOT);
    if (file_exists("$MY_LIBS_ROOT/$lib_name/php/index.php")) {
      /** @noinspection PhpIncludeInspection */
      require_once "$MY_LIBS_ROOT/$lib_name/php/index.php";
      return;
    }
    // else doesn't need to be handled , as it's ok to debug (to have a local copy of) only one of several libs
  }

  $ALL_LIBS_ROOT = EXTERNAL_KPHP_LIBS_POLYFILLS_ROOT;
  /** @noinspection PhpIncludeInspection */
  require_once "$ALL_LIBS_ROOT/$lib_name/php/index.php";
}


function vk_json_encode_safe($v) {
  return vk_json_encode($v);
}

function vk_dot_product(array $aVector, array $bVector): float {
  assert(count($aVector) == count($bVector));
  $result = 0;
  for ($i = 0; $i < count($aVector); ++$i) {
    $result += $aVector[$i] * $bVector[$i];
  }
  return $result;
}


function likely(bool $value): bool {
  return $value;
}

function unlikely(bool $value): bool {
  return $value;
}


#endregion


#endif
