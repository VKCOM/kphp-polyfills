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

#region constants

define('KPHP_COMPILER_VERSION', '');

#endregion

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
  // turn off PhpStorm native inferring
  return ${'any_value'};
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
  // turn off PhpStorm native inferring
  return ${'any_value'};
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
function instance_cast(?object $instance, string $class_name) {
  if ($instance === null) {
    return null;
  }
  if (!($instance instanceof $class_name)) {
    return null;
  }
  return $instance;  // it just returns the argument, but KPHP infers it as SomeClass
}

/**
 * to_array_debug($any_object) : mixed[] - deep convertation to object to array
 * Similar to (array)$any_object, but deep and with strict behaviour.
 * Useful for logging and debugging.
 * For all classes that are array-converted, KPHP generates an effective C++ visitor.
 * @param bool   $with_class_names Should the resulting array contain class names
 * @param bool   $public_members_only Should the resulting array contain only public fields
 * @return mixed[]
 */
function to_array_debug($any, $with_class_names = false, $public_members_only = false) {
  // (array) $instance in PHP outputs private/protected fields as '\0ClassName\0fieldName'
  // kphp omit such control characters
  $isPrivateField = function($key) { return $key[0] === "\0"; };

  $demangleField = function($key) use ($isPrivateField) {
    if ($isPrivateField($key)) {
      $key = preg_replace("/\\0.+\\0/", '', $key);
    }
    return $key;
  };

  $toArray = function($v) use (&$toArray, &$demangleField, &$with_class_names, $public_members_only, $isPrivateField) {
    if (is_object($v)) {
      $result = [];
      foreach ((array)$v as $field => $value) {
        if ($public_members_only && $isPrivateField($field)) {
          continue;
        }
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

  if ($any === null) {
    return [];
  }
  return $toArray($any);
}

/**
 * @see to_array_debug function
 */
function instance_to_array($instance, $with_class_names = false) {
  return to_array_debug($instance, $with_class_names);
}

/**
 * classof($obj) is a KPHP construct to express some logic in generics, like
 * `instance_cast($arg, classof($obj))`
 * In KPHP, it works at compile-time, no such runtime function exists.
 * In PHP, it just returns get_class(), here we can't do it better, though it's not generally true, use with care.
 */
function classof(object $obj): string {
  return get_class($obj);
}


class JsonEncoder {
  // these constants can be overridden in child classes
  const rename_policy     = 'none';
  const visibility_policy = 'all';
  const skip_if_default   = false;
  const float_precision   = 0;

  private function __construct() { }

  public static function encode(?object $instance, int $flags = 0, array $more = []): string {
    if ($instance === null) {
      return 'null';
    }
    try {
      self::$lastError = '';
      $serializer = new KPHP\JsonSerialization\JsonSerializer($instance, get_class($instance), static::class);
      return $serializer->serializeInstanceToJson($flags, $more);
    } catch (Throwable $e) {
      self::$lastError = $e->getMessage();
      return '';
    }
  }

  public static function decode(string $json_string, string $class_name): ?object {
    try {
      self::$lastError = '';
      $deserializer = new KPHP\JsonSerialization\JsonDeserializer($class_name, static::class);
      return $deserializer->unserializeInstanceFromJson($json_string);
    } catch (\JsonException $ex) {
      self::$lastError = $json_string === '' ? 'provided empty json string' : 'failed to parse json string: ' . $ex->getMessage();
      return null;
    } catch (Throwable $ex) {
      self::$lastError = $ex->getMessage();
      return null;
    }
  }

  public static function getLastError(): string {
    return self::$lastError;
  }

  private static string $lastError = '';
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

function wait($id, $timeout = -1.0) {
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

/**
 * @param int[] $query_ids
 * @return array
 */
function typed_rpc_tl_query_result_synchronously(array $query_ids) {
    return typed_rpc_tl_query_result($query_ids);
}

function get_running_fork_id(): int {
  return 0;
}

/**
 * @return string|false
 */
function curl_exec_concurrently($curl_handle, float $timeout_sec = 1.0) {
  curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
  if ($timeout_sec > 0) {
    curl_setopt($curl_handle, CURLOPT_TIMEOUT_MS, (int)($timeout_sec * 1000));
  }
  return curl_exec($curl_handle);
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
 *
 * Note that the result can differ between PHP and KPHP for an empty array:
 * * In PHP the function returns `null` regardless the array type, so actual return type is `?T`.
 * * In KPHP the function always returns result of type `T`.
 *
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function array_first_value(array $a) {
  return $a ? reset($a) : null;
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
 *
 * Note that the result can differ between PHP and KPHP for an empty array:
 * * In PHP the function returns `null` regardless the array type, so actual return type is `?T`.
 * * In KPHP the function always returns result of type `T`.
 *
 * @ return can't be expressed in phpdoc, done via KPHPStorm plugin for IDE
 */
function array_last_value(array $a) {
  return $a ? end($a) : null;
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
 * Effective especially for vectors, as there will be no reallocation on insertion.
 *
 * Note: This is a low level function. For more user-friendly API check following aliases:
 * @see array_reserve_vector
 * @see array_reserve_map_int_keys
 * @see array_reserve_map_string_keys
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
 * More useful @see array_reserve() alias for map with string keys.
 * Similar to calling: array_reserve($arr, $capacity, 0, true).
 *
 * @param array $arr          Target array (vector/map)
 * @param int   $capacity     Amount of the elements (for vector) or int keys (for map with int keys)
 */
function array_reserve_vector(&$arr, $capacity) {
    // in PHP does nothing
}

/**
 * More useful @see array_reserve() alias for map with int keys.
 * Similar to calling: array_reserve($arr, $capacity, 0, false).
 *
 * @param array $arr          Target array (vector/map)
 * @param int   $int_keys_num Amount of int keys
 */
function array_reserve_map_int_keys(&$arr, $int_keys_num) {
    // in PHP does nothing
}

/**
 * More useful @see array_reserve() alias for map with string keys.
 * Similar to calling: array_reserve($arr, 0, $capacity, false).
 *
 * @param array $arr          Target array (vector/map)
 * @param int   $str_keys_num Amount of string keys
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
 * array_unset(T[], $key) : T - unset value in array and return it
 */
function array_unset(array &$arr, $key) {
  $res = $arr[$key];
  unset($arr[$key]);
  return $res;
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
  KPHP\MsgPackSerialization\ClassTransformer::$depth = 0;
  return _php_serialize_helper_run_or_warning(static function() use ($instance) {
    $packer = (new MessagePack\Packer(MessagePack\PackOptions::FORCE_STR))->extendWith(new KPHP\MsgPackSerialization\ClassTransformer());
    return $packer->pack($instance);
  });
}

function instance_serialize_safe(object $instance): string {
  KPHP\MsgPackSerialization\ClassTransformer::$depth = 0;
  $packer = (new MessagePack\Packer(MessagePack\PackOptions::FORCE_STR))->extendWith(new KPHP\MsgPackSerialization\ClassTransformer());
  return $packer->pack($instance);
}

function instance_deserialize(string $packed_str, string $type_of_instance): ?object {
  return _php_serialize_helper_run_or_warning(static function() use ($packed_str, $type_of_instance) {
    $unpacked_array = msgpack_deserialize_safe($packed_str);

    $instance_parser = new KPHP\MsgPackSerialization\MsgPackDeserializer($type_of_instance);
    return $instance_parser->fromUnpackedArray($unpacked_array);
  });
}

function instance_deserialize_safe(string $packed_str, string $type_of_instance): ?object {
  $unpacked_array = msgpack_deserialize_safe($packed_str);
  $instance_parser = new KPHP\MsgPackSerialization\MsgPackDeserializer($type_of_instance);
  return $instance_parser->fromUnpackedArray($unpacked_array);
}

/**
 * @param mixed $value
 */
function msgpack_serialize($value): ?string {
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

/**
 * Returns dictionary with the following stats:
 * 'memory_limit'          - max memory available (512MB by default)
 * 'real_memory_used'      - right bound at memory arena
 * 'memory_used'           - total memory currently used
 * 'max_real_memory_used'  - max of 'real_memory_used'
 * 'max_memory_used'       - max of 'memory_used'
 * 'defragmentation_calls' - the total number of defragmentation process calls
 * 'huge_memory_pieces'    - the number of huge memory pirces (in rb tree)
 * 'small_memory_pieces'   - the number of small memory pieces (in lists)
 * 'heap_memory_used'      - total heap memory currently used
 * @return int[]
 */
function memory_get_detailed_stats() {
  return [];
}

/**
 * Returns tuple of (allocations_count, allocated_total).
 * @return tuple(int, int)
 */
function memory_get_allocations() {
  return tuple(0, 0);
}

#endregion


#region job workers

// contains declarations for KPHP job workers api
// it has NO PHP IMPLEMENTATION; kphp_job_worker_start() isn't supposed to be called in plain PHP

/**
 * Job workers are separate processes which can be invoked from HTTP workers to parallelize your PHP code.
 * Unlike forks, that are coroutines actually, job workers can parallelize CPU-consuming execution.
 * @see kphp_job_worker_start accepting \KphpJobWorkerRequest and returning future<\KphpJobWorkerResponse>|false
 * @see wait accepting future<T>|false and returning ?T
 * To handle job requests in an entrypoint, test for $_SERVER["JOB_ID"]
 */
interface KphpJobWorkerRequest {
}

/**
 * Job workers are separate processes which can be invoked from HTTP workers to parallelize your PHP code.
 * @see \KphpJobWorkerRequest
 */
interface KphpJobWorkerResponse {
}

/**
 * Classes marked with this interface can be shared across multiple job workers (for reading only due to immutability).
 * This is used for optimization: it allows copying an object only once while launching multiple job workers.
 * Consider the docs for details.
 * @see kphp_job_worker_start_multi
 * @kphp-immutable-class
 */
interface KphpJobWorkerSharedMemoryPiece {
}

/**
 * When a job worker fails, this built-in instance is returned from wait().
 * In other words, it's an error result for future<KphpJobWorkerResponse>
 * Hence, having called wait(), you should test the result with instanceof, handling an error and probably providing a local execution path.
 */
class KphpJobWorkerResponseError implements KphpJobWorkerResponse {
  // Job script execution errors:
  const JOB_MEMORY_LIMIT_ERROR = -101;
  const JOB_TIMEOUT_ERROR = -102;
  const JOB_EXCEPTION_ERROR = -103;
  const JOB_STACK_OVERFLOW_ERROR = -104;
  const JOB_PHP_ASSERT_ERROR = -105;

  const JOB_CLIENT_MEMORY_LIMIT_ERROR = -1001; // client doesn't have enough memory to accept job response
  const JOB_NOTHING_REPLIED_ERROR = -2001;     // kphp_job_worker_store_response() was not succeeded

  const JOB_STORE_RESPONSE_INCORRECT_CALL_ERROR = -3000;
  const JOB_STORE_RESPONSE_NOT_ENOUGH_SHARED_MESSAGES_ERROR = -3001;
  const JOB_STORE_RESPONSE_TOO_BIG_ERROR = -3002;
  const JOB_STORE_RESPONSE_CANT_SEND_ERROR = -3003;

  public function getError(): string {
    return '';
  }

  public function getErrorCode(): int {
    return 0;
  }
}


/**
 * Returns whether KPHP is launched with at least one job worker process.
 * Your code must be always prepared to provide a local execution path if job workers are turned off on KPHP launch.
 */
function is_kphp_job_workers_enabled(): bool {
  return false;
}

/**
 * Returns a number of job worker processes launched on KPHP start. This number remains the same after starting.
 * This can be used to split job tasks to batches: instead of using a fixed-size splitting, you use percentage of workers.
 */
function get_job_workers_number(): int {
  return 0;
}

/**
 * Starts a job worker request that will be handled in a separate KPHP process.
 * Internally, it deeply copies $request to a shared memory buffer that would be available for a job process for reading.
 * Job workers can be called from job workers.
 * There is no PHP implementation: the caller side must provide a local fallback when there are no job workers available.
 * The response can be achieved using @see wait
 * @return future<KphpJobWorkerResponse> | false
 */
function kphp_job_worker_start(KphpJobWorkerRequest $request, float $timeout) {
  warning("kphp_job_worker_start() should be used in KPHP only");
  return false;
}

/**
 * Starts multiple job workers at once, which can share the same shared memory piece as a request class field.
 * Consider the docs for details.
 * @see KphpJobWorkerSharedMemoryPiece
 * @param KphpJobWorkerRequest[] $requests
 * @return (future<KphpJobWorkerResponse> | false)[]
 */
function kphp_job_worker_start_multi(array $requests, float $timeout) {
  warning("kphp_job_worker_start_multi() should be used in KPHP only");
  return [];
}

/**
 * Starts a job worker that will never reply, wait() can't be called for it.
 * Used to initiate a background process that will probably continue after http worker's death.
 * @return bool true|false instead of future|false (this boolean means whether a job was added to queue)
 */
function kphp_job_worker_start_no_reply(KphpJobWorkerRequest $request, float $timeout): bool {
  warning("kphp_job_worker_start_no_reply() should be used in KPHP only");
  return false;
}

/**
 * Deserializes a job request from a shared memory buffer to a script-visible memory.
 * Works only when the current KPHP process is launched as a job worker (test for $_SERVER["JOB_ID"] in a PHP entrypoint).
 */
function kphp_job_worker_fetch_request(): ?KphpJobWorkerRequest {
  warning("kphp_job_worker_fetch_request() should be used in KPHP only");
  return null;
}

/**
 * Serializes a server job response to a shared memory buffer passing a response back to the caller.
 * Works only when the current KPHP process is launched as a job worker (test for $_SERVER["JOB_ID"] in a PHP entrypoint).
 * @return int 0 on success, < 0 - on errors. All possible error codes are constants at KphpJobWorkerResponseError
 */
function kphp_job_worker_store_response(KphpJobWorkerResponse $response): int {
  warning("kphp_job_worker_store_response() should be used in KPHP only");
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

function register_kphp_on_oom_callback(callable $callback): bool {
  return false;
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

/** @return mixed */
function kphp_get_runtime_config() {
  // in PHP, do nothing;
  // in KPHP it is a built-in function that returns the runtime configuration that was set when the server started
  return null;
}
/**
 * Do test thread pool load
 * @param int $size count of threads
 * @param int $n number of calculations per thread
 * @param float $a first parameter
 * @param float $b second parameter
 *
 * @return float
 */
function thread_pool_test_load(int $size, int $n, float $a, float $b) {
    return 0.0;
}

/** @return tuple(int, int, int, int) */
function get_webserver_stats() {
    //in PHP, do nothing;
    //in KPHP it is built-in function that returns buffered webserver information
    return tuple(0, 0, 0, 0);
}

/**
 * Get cluster name passed to KPHP server. In PHP does nothing
 * @return string
 */
function get_kphp_cluster_name(): string {
  return "";
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

function set_json_log_on_timeout_mode(bool $enabled): void {
}

function vk_dot_product(array $aVector, array $bVector): float {
  assert(count($aVector) == count($bVector));
  $result = 0;
  for ($i = 0; $i < count($aVector); ++$i) {
    $result += $aVector[$i] * $bVector[$i];
  }
  return $result;
}

/**
 * Sends an HTTP 103 header — an intermediate header, in the middle of script execution, before sending 200 OK.
 * It's used for Web, to force a browser to preload css/js/other static.
 * Typical usage is: start handling request -> send 103 -> prepare full response -> send 200.
 * Warning! Be sure to test via user-agent whether a client's browser would correctly accept this header.
 * @param string[] $headers
 */
function send_http_103_early_hints(array $headers) {
  // in PHP, do nothing;
  // in KPHP, it's a built-in function that sends $headers as passed bypassing response buffers
  // all `header()` calls made before sending 103 remain buffered, they will be applied on 200 response
}


function likely(bool $value): bool {
  return $value;
}

function unlikely(bool $value): bool {
  return $value;
}


/**
 * CompileTimeLocation is a KPHP built-in class which allows getting a caller location inside a function.
 * In PHP, you write:
 *   function log_info(string $message, CompileTimeLocation $loc = null) {
 *     $loc = CompileTimeLocation::calculate($loc);
 *     echo "$message (at {$loc->file}:{$loc->line})";
 *   }
 *   log_info("start");  // $loc inside log_info() will contain file/function/line of this exact call
 * In PHP, it works at runtime using debug_backtrace().
 * In KPHP, it works at compile-time, since KPHP implicitly inserts an argument on a call
 * replacing log_info($x) with log_info($x, new CompileTimeLocation(__FILE__, __METHOD__, __LINE)).
 */
class CompileTimeLocation {
  public string $file;
  public string $function;
  public int $line;

  private function __construct() { }

  static public function calculate(?CompileTimeLocation $passed): CompileTimeLocation {
    if ($passed !== null) {
      return $passed;
    }

    $t = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    $loc = new CompileTimeLocation;

    $loc->file = $t[1]['file'];
    $loc->function = isset($t[2]['class']) ? "{$t[2]['class']}::{$t[2]['function']}" : ($t[2]['function'] ?? '');
    $loc->line = $t[1]['line'];
    return $loc;
  }
}


#endregion

/**
 * PHP 8 has changed the logic for comparing numbers and strings,
 * as well as changes in the concept of a numeric string.
 *
 * The function takes a bit mask:
 * - 0b001: Enable warning that comparison between numeric strings and numbers has a different result than in PHP 7.
 * - 0b010: Enable warning that converting to float for a string has a different result than in PHP 7.
 *
 * @param int $mask
 */
function set_migration_php8_warning(int $mask): void {}

/**
 * Enabling the mode for search for incorrect encoding names.
 */
function set_detect_incorrect_encoding_names_warning(bool $show): void {}

/**
 * Enabling new version of gmmktime function
 */
function set_use_updated_gmmktime(bool $enable): void {}

/**
 * Enabling demangled stacktrace in json logs
 */
function set_json_log_demangle_stacktrace(bool $enable): void {}

/**
 * Nobody except KPHP team should use this function in production!
 *
 * Enables host tag in all inner kphp StatsHouse metrics for next 30 sec
 */
function kphp_turn_on_host_tag_in_inner_statshouse_metrics_toggle(): void {}

/**
 * Gives a ballpark size estimate of the given value, in bytes.
 * Results may differ greatly from KPHP
 */
function estimate_memory_usage($value, int $depth = 0): int {
  // works 10x times faster than iterating over array elements, reflection on objects etc.
  return strlen(serialize($value));
}


#region ffi


/**
 * ffi_memcpy_string implements FFI::memcpy for string-typed $src argument
 */
function ffi_memcpy_string(\FFI\CData $dst, string $src, int $size) {
  \FFI::memcpy($dst, $src, $size);
}

/**
 * identical to FFI::cast('uintptr_t', $ptr)
 * the addr obtained from this function can be used with ffi_cast_addr2ptr
 * @param ffi_cdata<C, void*> $ptr
 * @return int
 */
function ffi_cast_ptr2addr($ptr) {
  // For some reason, PHP doesn't like void* cast to scalar.
  // As a temporary (?) workaround, cast void* to some sized pointer first.
  $as_sized_ptr = \FFI::cast('uint8_t*', $ptr);
  return \FFI::cast('uintptr_t', $as_sized_ptr)->cdata;
}

/**
 * identical to FFI::cast('void*', $addr)
 * $addr should be obtained from ffi_cast_ptr2addr
 * @param int $addr
 * @return ffi_cdata<C, void*>
 */
function ffi_cast_addr2ptr($addr) {
  return \FFI::cast('void*', $addr);
}

/**
 * ffi_array_set implements array or pointer update operation: $arr[$index] = $value
 * For CData arrays, a bound check if performed: PHP throws and KPHP triggers
 * a critical error if $index is out of bounds
 */
function ffi_array_set(\FFI\CData $arr, int $index, $value): void {
  $arr[$index] = $value;
}

/**
 * ffi_array_get implements array or pointer read operation: $arr[$index]
 * For CData arrays, a bound check if performed: PHP throws and KPHP triggers
 * a critical error if $index is out of bounds
 * @return \FFI\CData
 */
function ffi_array_get(\FFI\CData $arr, int $index) {
  return $arr[$index];
}


#endregion


#region kphp_tracing

// KPHP tracing is a technology that aims to collect richer variety of data compared to Open telemetry
// and faster compared to existing tracing SDKs.
// In fact, every single request is traced in-memory with zero overhead, and probably flushed to disk on finish
// (if something interesting happens during a script execution, we have its trace — for any given request).
//
// KPHP tracing is not implemented for PHP at all (and has no reason to be).
// In PHP, all tracing functions do nothing.

/**
 * "Div" represents a piece of a distributed Trace written by a single process handling one request.
 * It's a set of spans (@see KphpSpan below).
 * In case of KPHP, when a worker starts handling a PHP request, it starts a div,
 * which is finished on php script finish (no matter whether it's a general/job/rpc worker).
 * A term "Div" was invented specially, not to be confused with a word "Trace":
 * Trace is a set of Div with equal TraceID, it's a distributed entity,
 * whereas Div is started and finished by a single trace-emitting node.
 */
final class KphpDiv {
  /**
   * @return tuple(int, int)
   */
  public function generateTraceCtxForChild(int $div_id, int $trace_flags) {
    return tuple(0, 0);
  }

  public function assignTraceCtx(int $int1, int $int2, ?int $override_div_id): int {
    return 0;
  }

  public function getStartTimestamp(): float {
    return 0.0;
  }

  public function getEndTimestamp(): float {
    return 0.0;
  }
}

/**
 * Span is an atomic element inside a Div.
 * As opposed to Open telemetry, KPHP does not store any state of spans: it does not have a Span class
 * containing start/finish timestamps, name, parent, children, attributes, links, etc.
 * Instead, when any event occurs, it is written to a binary in-memory append-only log (kphp_tracing_binlog.h).
 * That binlog is a compact representation throughout all trace timeline.
 *
 * As a consequence, addAttribute() exists, but getAttribute() does not.
 * updateName() exists, but getName() does not. And so on.
 */
final class KphpSpan {
  private function __construct() {
  }

  static public function dummyPhpSpan(): KphpSpan {
    return new self();
  }

  public function addAttributeString(string $key, string $value) {}
  public function addAttributeInt(string $key, int $value) {}
  public function addAttributeFloat(string $key, float $value) {}
  public function addAttributeBool(string $key, bool $value) {}
  public function addAttributeEnum(string $key, int $enum_id, int $value) {}

  public function addLink(\KphpSpan $another) {}
  public function addEvent(string $name, ?float $timestamp = null): \KphpSpanEvent {
    return \KphpSpanEvent::dummyPhpSpanEvent();
  }

  public function updateName(string $title, string $short_desc) {}
  public function finish(?float $end_timestamp = null) {}
  public function finishWithError(int $error_code, string $error_msg, ?float $end_timestamp = null) {}
  public function exclude() {}
}

/**
 * Returned by KphpSpan::addEvent(), can accept only attributes (which are also written to binlog).
 * Actually, it's a special case of a span with kind=KindSpanEvent.
 */
final class KphpSpanEvent {
  private function __construct() {
  }

  static public function dummyPhpSpanEvent(): KphpSpanEvent {
    return new self();
  }

  public function addAttributeString(string $key, string $value) {}
  public function addAttributeInt(string $key, int $value) {}
  public function addAttributeFloat(string $key, float $value) {}
  public function addAttributeBool(string $key, bool $value) {}
}

// global tracing functions from _functions.txt

function kphp_tracing_init(string $root_span_title): KphpDiv {
  return new KphpDiv();
}

function kphp_tracing_set_level(int $trace_level): void {
}

function kphp_tracing_get_level(): int {
  return 0;
}

function kphp_tracing_register_on_finish(callable $cb_should_be_flushed) {}
function kphp_tracing_register_enums_provider(callable $cb_custom_enums) {}
function kphp_tracing_register_rpc_details_provider(callable $cb_for_typed, callable $cb_for_untyped) {}

function kphp_tracing_start_span(string $title, string $short_desc, float $start_timestamp): KphpSpan {
  return KphpSpan::dummyPhpSpan();
}

function kphp_tracing_get_root_span(): KphpSpan {
  return KphpSpan::dummyPhpSpan();
}

function kphp_tracing_get_current_active_span(): KphpSpan {
  return KphpSpan::dummyPhpSpan();
}

function kphp_tracing_func_enter_branch(int $branch_num) {}


#endregion


#endif
