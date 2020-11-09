<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpDocMissingReturnTagInspection */

/*
 * This file contains declarations of available UberH3 functions.
 * They are implemented with C in uber-php-h3 lib for plain PHP and supported by KPHP natively.
 *
 * !!! File is used only to provide accurate autocompletion for IDE and must not be required.
 */

#ifndef KPHP

exit("This file should not be included, only analyzed by your IDE");

/** @linter ignore unusedType until 2040 */
class UberH3 {
  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/indexing.md#geotoh3
   *
   * @param float $latitude - in degrees
   * @param float $longitude - in degrees
   * @param int $resolution - value in [0, 15] interval
   */
  static public function geoToH3(float $latitude, float $longitude, int $resolution) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/indexing.md#h3togeo
   *
   * @return tuple(float, float)
   *    tuple[0] - latitude in degrees,
   *    tuple[1] - longitude in degrees
   */
  static public function h3ToGeo(int $h3_index) {
    return tuple(0.0, 0.0);
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/indexing.md#h3togeoboundary
   *
   * @return tuple(float, float)[]
   *    tuple[0] - latitude in degrees,
   *    tuple[1] - longitude in degrees
   */
  static public function h3ToGeoBoundary(int $h3_index) : array {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/inspection.md#h3getresolution
   */
  static public function h3GetResolution(int $h3_index) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/inspection.md#h3getbasecell
   */
  static public function h3GetBaseCell(int $h3_index) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/inspection.md#stringtoh3
   */
  static public function stringToH3(string $h3_index_str) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/inspection.md#h3tostring
   */
  static public function h3ToString(int $h3_index) : string {
      return "";
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/inspection.md#h3isvalid
   */
  static public function h3IsValid(int $h3_index) : bool {
    return false;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/inspection.md#h3isresclassiii
   */
  static public function h3IsResClassIII(int $h3_index) : bool {
    return false;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/inspection.md#h3ispentagon
   */
  static public function h3IsPentagon(int $h3_index) : bool {
    return false;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/inspection.md#h3getfaces
   *
   * @return int[]
   */
  static public function h3GetFaces(int $h3_index) : array {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/inspection.md#maxfacecount
   */
  static public function maxFaceCount(int $h3_index) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#kring
   *
   * @param int $k - value in [0, int32 max] interval
   * @return int[]|false
   */
  static public function kRing(int $h3_index, int $k) {
      return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#maxkringsize
   */
  static public function maxKringSize(int $k) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#kringdistances
   *
   * @param int $k - value in [0, int32 max] interval
   * @return tuple(int, int)[]|false
   *    tuple[0] - neighbor h3 index
   *    tuple[1] - distance between origin h3 index and neighbor h3 index
   */
  static public function kRingDistances(int $h3_index_origin, int $k) {
     return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#hexrange
   *
   * @param int $k - value in [0, int32 max] interval
   * @return int[]|false
   */
  static public function hexRange(int $h3_index_origin, int $k) {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#hexrangedistances
   *
   * @param int $k - value in [0, int32 max] interval
   * @return tuple(int, int)[]|false
   *    tuple[0] - neighbor h3 index
   *    tuple[1] - distance between origin h3 index and neighbor h3 index
   */
  static public function hexRangeDistances(int $h3_index_origin, int $k) {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#hexranges
   *
   * @param int[] $h3_indexes
   * @param int $k - value in [0, int32 max] interval
   * @return int[]|false
   */
  static public function hexRanges(array $h3_indexes, int $k) {
      return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#hexring
   *
   * @param int $k - value in [0, int32 max] interval
   * @return int[]|false
   */
  static public function hexRing(int $h3_index_origin, int $k) {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#h3line
   *
   * @return int[]|false
   */
  static public function h3Line(int $h3_index_start, int $h3_index_end) {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#h3linesize
   */
  static public function h3LineSize(int $h3_index_start, int $h3_index_end) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/traversal.md#h3distance
   */
  static public function h3Distance(int $h3_index_start, int $h3_index_end) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/hierarchy.md#h3toparent
   *
   * @param int $parent_resolution - value in [0, 15] interval
   */
  static public function h3ToParent(int $h3_index, int $parent_resolution) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/hierarchy.md#h3tochildren
   *
   * @param int $children_resolution - value in [0, 15] interval
   * @return int[]|false
   */
  static public function h3ToChildren(int $h3_index, int $children_resolution) {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/hierarchy.md#maxh3tochildrensize
   *
   * @param int $children_resolution - value in [0, 15] interval
   */
  static public function maxH3ToChildrenSize(int $h3_index, int $children_resolution) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/hierarchy.md#h3tocenterchild
   *
   * @param int $children_resolution - value in [0, 15] interval
   */
  static public function h3ToCenterChild(int $h3_index, int $children_resolution) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/hierarchy.md#compact
   *
   * @param int[] $h3_indexes
   * @return int[]|false
   */
  static public function compact(array $h3_indexes) {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/hierarchy.md#uncompact
   *
   * @param int[] $h3_indexes
   * @return int[]|false
   */
  static public function uncompact(array $h3_indexes, int $resolution) {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/hierarchy.md#maxuncompactsize
   *
   * @param int[] $h3_indexes
   */
  static public function maxUncompactSize(array $h3_indexes, int $resolution) : int {
    return 0;
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/regions.md#polyfill
   *
   * @param tuple(float, float)[] $polygon_boundary - exterior boundary of the polygon
   * @param tuple(float, float)[][] $holes - interior boundaries (holes) in the polygon
   *    For both $polygon_boundary and $holes:
   *      tuple[0] - latitude in degrees
   *      tuple[1] - longitude in degrees
   * @return int[]|false
   */
  static public function polyfill(array $polygon_boundary, array $holes, int $resolution) {
    return [];
  }

  /**
   * @link https://github.com/uber/h3/blob/master/docs/api/regions.md#maxpolyfillsize
   *
   * @param tuple(float, float)[] $polygon_boundary - exterior boundary of the polygon
   * @param tuple(float, float)[][] $holes - interior boundaries (holes) in the polygon
   *    For both $polygon_boundary and $holes:
   *      tuple[0] - latitude in degrees
   *      tuple[1] - longitude in degrees
   */
  static public function maxPolyfillSize(array $polygon_boundary, array $holes, int $resolution) : int {
    return 0;
  }
}

#endif
