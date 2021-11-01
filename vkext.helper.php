<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection PhpUnused */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpComposerExtensionStubsInspection */
/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpDocMissingReturnTagInspection */
/** @noinspection KphpDocInspection */
/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

/*
 * This file contains declarations of KPHP native functions that are written in C (vkext.so - PHP extension).
 * So, while executing a script im plain PHP - vkext implementation is used,
 * but after compilation they are replaced by built-in ones.
 *
 * This functions can't be polyfilled in plain PHP (or PHP versions would be very slow),
 * that's why they are written in C.
 *
 * !!! File is used only to provide accurate autocompletion for IDE and must not be required.
 */

#ifndef KPHP

define('KPHP_COMPILER_VERSION', '');

/**
 * Converts string in utf8 to string in cp1251 with html-entities.
 *
 * @param string $s
 * @param int $max_len
 * @param bool $exit_on_error
 * @return string
 */
function vk_utf8_to_win($s, $max_len = 0, $exit_on_error = false) {
  return "";
}

/**
 * Converts string in cp1251 with html-entities to string in utf8.
 *
 * @param string $s
 * @return string
 */
function vk_win_to_utf8($s) {
  return "";
}

/**
 * @param string $name
 * @param string $case_name
 * @param int $sex
 * @param string $type
 * @param int $lang_id
 * @return string
 */
function vk_flex($name, $case_name, $sex, $type, $lang_id = 0) {
  return "";
}

/**
 * @param mixed $v
 * @return string
 */
function vk_json_encode($v) {
  return "";
}

/**
 * @param mixed $v
 * @return string
 */
function vk_json_encode_safe($v) {
  return "";
}

/**
 * @param string $str
 * @param bool $html_opt
 * @return string
 */
function vk_whitespace_pack($str, $html_opt = false) {
  return "";
}

/**
 * Returns RPC connection to $host:$port, with given default actor_id and timeouts.
 *
 * @param string $host
 * @param int $port
 * @param int $default_actor_id
 * @param float $default_timeout
 * @param float $connect_timeout
 * @param float $reconnect_timeout
 * @return resource|false
 */
function new_rpc_connection($host, $port, $default_actor_id = 0, $default_timeout = 0.3, $connect_timeout = 0.3, $reconnect_timeout = 17.0) {
  return 0;
}

/**
 * Creates an rpc_queue, returns it id. Push $request_ids to it.
 *
 * @param array $request_ids
 * @return int
 */
function rpc_queue_create($request_ids = []) {
  return 0;
}

/**
 * Check if rpc_queue with id queue_id is empty.
 *
 * @param int $queue_id
 * @return bool
 */
function rpc_queue_empty($queue_id) {
  return true;
}

/**
 * Waits until any of requests in queue finished and returns id of any finished query in queue.
 * Returned query is removed from queue.
 * If timeout is not provided no timeout used.
 *
 * @param int $queue_id
 * @param float $timeout
 * @return int|false
 */
function rpc_queue_next($queue_id, $timeout = -1.0) {
  return 0;
}

/**
 * @param int $queue_id
 * @return int|false
 */
function rpc_queue_next_synchronously($queue_id) {
  return 0;
}

/**
 * Pushes all $queries to queue with id $queue_id.
 * Every queue can be pushed only to one queue only once.
 * Used by rpc_tl_query_result.
 * Return value should be ignored. It is different in php in kphp.
 *
 * @param int $queue_id
 * @param array|int $queries
 * @return void
 */
function rpc_queue_push($queue_id, $queries) {
}

/**
 * @see rpc_tl_query_result, rpc_tl_query_result_one, new_rpc_connection
 *
 * Given a rpc_connection ($connection) and array of rpc-queries ($queries) send them and returns
 * queries ids, which can be passed to rpc_tl_query_result.
 *
 * If no $timeout given default timeout for connection is used.
 *
 * If $ignore_result is true, query id will be equal to -1, and
 * result can't be received.
 *
 * @param resource $connection
 * @param array $queries
 * @param float $timeout
 * @param bool $ignore_result
 * @return array
 */
function rpc_tl_query($connection, $queries, $timeout = -1.0, $ignore_result = false) {
  return [];
}

/**
 * @see rpc_tl_query_result, rpc_tl_query_result_one, new_rpc_connection
 *
 * Given a rpc_connection ($connection) and an rpc-query ($query) send it and returns
 * query id, which can be passed to rpc_tl_query_result or rpc_tl_query_result_one.
 *
 * If no $timeout given default timeout for connection is used.
 *
 * @param resource $connection
 * @param array $query
 * @param float $timeout
 * @return int
 */
function rpc_tl_query_one($connection, $query, $timeout = -1.0) {
  return 0;
}

/**
 * @see rpc_tl_query
 *
 * Given an array of rpc-queries ids returns result of this queries.
 * Each result can be gotten only once.
 *
 * @param array $query_ids
 * @return array
 */
function rpc_tl_query_result($query_ids) {
  return [];
}

/**
 * @see rpc_tl_query, rpc_tl_query_one
 *
 * Given an rpc-query id returns result of this query.
 * Result can be gotten only once.
 *
 * @param int $query_id
 * @return array
 */
function rpc_tl_query_result_one($query_id) {
  return [];
}

/**
 * @return array
 */
function rpc_get_last_send_error() {
  return [];
}

/**
 * Returns version of extension, including commit and compile date
 *
 * @return string
 */
function vkext_full_version() {
  return "";
}

/**
 * Simplify a string, like simplify in logs/antispam engine. Returns new string.
 * Returns empty string on error.
 *
 * @param string $s
 * @return string
 */
function vk_sp_simplify($s) {
  return "";
}

/**
 * Simplify a string, like full_simplify in logs/antispam engine. Returns new string.
 * Returns empty string on error.
 *
 * @param string $s
 * @return string
 */
function vk_sp_full_simplify($s) {
  return "";
}

/**
 * Simplify a string, by changing html entities (like &lq;) to ascii symbols. Returns new string.
 * Returns empty string on error.
 *
 *
 * @param string $s
 * @return string
 */
function vk_sp_deunicode($s) {
  return "";
}

/**
 * Returns new string, where all characters are changed to uppercase. cp1251 is used for russian.
 * Returns empty string on error.
 *
 * @param string $s
 * @return string
 */
function vk_sp_to_upper($s) {
  return "";
}

/**
 * Returns new string, where all characters are changed to lowercase. cp1251 is used for russian.
 * Returns empty string on error.
 *
 * @param string $s
 * @return string
 */
function vk_sp_to_lower($s) {
  return "";
}

/**
 * Changes string, like sort in logs engine. Returns new string.
 * Returns empty string on error.
 *
 * @param string $s
 * @return string
 */
function vk_sp_sort($s) {
  return "";
}

/**
 * Given array of elements and size of hll stat, returns an hll stat for this set.
 * Each non-int element in array is converted to int.
 * Size has to be equal to 256 or 16384.
 *
 * @param array $elements
 * @param int $size
 * @return string|false
 */
function vk_stats_hll_create($elements = [], $size = 256) {
  return "";
}

/**
 * Given hll stat string and array of elements, returns new hll stat
 * with all array elements added to given set.
 * Each non-int element in array is converted to int.
 * String has to contain unpacked hll of size 256 or 16384.
 *
 * @param string $hll
 * @param array $elements
 * @return string|false
 */
function vk_stats_hll_add($hll, $elements) {
  return "";
}

/**
 * Given array of hll stats, returns a hll stat for union of the sets.
 * Length of all hlls should be same.
 *
 * @param array $hlls
 * @return string|false
 */
function vk_stats_hll_merge($hlls) {
  return "";
}

/**
 * Given hll stat, returns an approximate number of different elements in set.
 *
 * @param string $hll
 * @return float|false
 */
function vk_stats_hll_count($hll) {
  return 0.0;
}

/**
 * @return string
 */
function get_engine_version() {
  return '';
}

/**
 * Fetch int from RPC-buffer
 * @return int
 */
function fetch_int() {
  return 0;
}

/**
 * Fetch long from RPC-buffer
 * @return mixed
 */
function fetch_long() {
  return '0';
}

/**
 * Fetch string from RPC-buffer
 * @return string
 */
function fetch_string() {
  return '';
}

/**
 * Fetch float from RPC-buffer
 * @return float
 */
function fetch_double() {
  return 0;
}

/**
 * Lookup int from RPC-buffer
 * @return int
 */
function fetch_lookup_int() {
  return 0;
}

/**
 * Lookup data of given length (in x4 bytes) from RPC-buffer
 * @return string
 */
function fetch_lookup_data($x4_bytes_length) {
  return '';
}

/**
 * Checks if all data were fetched
 * @return bool
 */
function fetch_eof() {
  return false;
}

/**
 * @param $errno
 * @param $errtext
 */
function store_error($errno, $errtext) {
}

/**
 * @param $int
 */
function store_int($int) {
}

/**
 * @param $str
 */
function store_long($str) {
}

/**
 * @param $str
 */
function store_string($str) {
}

/**
 * Flushes stored bytes to the network
 */
function rpc_flush() {
}

/**
 * Returns raw rpc result data for query $qid
 *
 * @param $qid int
 * @return string|false
 */
function rpc_get($qid) {
  return "";
}

/**
 * Setups rpc parse buffer to $data. Return true on success.
 *
 * @param $data string
 * @return bool
 */
function rpc_parse($data) {
  return true;
}

/**
 * Sends stored bytes to the $rpc_conn connection.
 * @param resource $rpc_conn
 * @param int $timeout
 * @return int
 */
function rpc_send($rpc_conn, $timeout = -1.0) {
  return 0;
}

/**
 * Sends stored bytes to the $rpc_conn connection without flush to the network.
 * @param resource $rpc_conn
 * @param int $timeout
 * @return int
 */
function rpc_send_noflush($rpc_conn, $timeout = -1.0) {
  return 0;
}

/**
 * Equal to rpc_parse(rpc_get($qid));
 *
 * @param $qid int
 * @return bool
 */
function rpc_get_and_parse($qid) {
  return true;
}

function rpc_clean() {
}

/**
 * @return int
 */
function rpc_tl_pending_queries_count() {
  return 0;
}

function store_finish() {
}

/**
 * @return string
 */
function vkext_prepare_stats() {
  return "";
}

/**
 * @param int $conn \RpcConnection
 * @param @tl\RpcFunction $request
 * @param float $timeout
 * @return int
 */
function typed_rpc_tl_query_one($conn, $request, $timeout = -1.0) {
  return 0;
}

/**
 * @param int $conn
 * @param @tl\RpcFunction[] $requests
 * @param float $timeout
 * @return array
 */
function typed_rpc_tl_query($conn, array $requests, $timeout = 0.0) {
  return [];
}

/**
 * @param int $query_id
 * @return @tl\RpcResponse
 */
function typed_rpc_tl_query_result_one($query_id) {
  return null;
}

/**
 * @param int[] $query_ids
 * @return array
 */
function typed_rpc_tl_query_result(array $query_ids) {
  return [];
}

/**
 * When storing an int32 TL value, if an int64 value doesn't fit, return a storing error and don't send a query.
 * @param bool $fail_rpc Enable this mode
 * @return bool
 */
function set_fail_rpc_on_int32_overflow(bool $fail_rpc): bool {
  return true;
}

/**
 * Deserializes a server RPC request to a corresponding typed TL class.
 * NB! Works only in KPHP - not in PHP - when it is launched as RPC server!
 * @return @tl\RpcFunction
 */
function rpc_server_fetch_request() {
  return null;
}

/**
 * Serializes a server RPC response based on TL scheme and stores it in RPC buffer.
 * NB! Works only in KPHP - not in PHP - when it is launched as RPC server!
 * @param @tl\RpcFunctionReturnResult $response
 */
function rpc_server_store_response($response) {
}

/**
 * Zstandard compression.
 *
 * @param  string $data
 * @param  int    $level
 * @return string|false
 */
function zstd_compress(string $data, int $level = 3) {
    return "";
}

/**
 * Zstandard decompression.
 *
 * @param  string $data
 * @return string|false
 */
function zstd_uncompress(string $data) {
    return "";
}

/**
 * Zstandard compression using a digested dictionary.
 *
 * @param  string $data
 * @param  string $dict
 * @return string|false
 */
function zstd_compress_dict(string $data, string $dict) {
    return "";
}

/**
 * Zstandard decompression using a digested dictionary.
 *
 * @param  string $data
 * @param  string $dict
 * @return string|false
 */
function zstd_uncompress_dict(string $data, string $dict) {
    return "";
}

exit("This file should not be included, only analyzed by your IDE");
#endif

