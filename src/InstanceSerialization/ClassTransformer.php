<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\InstanceSerialization;

use MessagePack\Packer;
use MessagePack\TypeTransformer\CanPack;
use ReflectionException;
use RuntimeException;

class ClassTransformer implements CanPack {
  /** @var int */
  public static $depth = 0;

  /** @var int */
  public static $max_depth = 20;

  /**
   * @param Packer $packer
   * @param object $instance
   * @return string|null
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function pack(Packer $packer, $instance): ?string {
    $instance_parser = new InstanceParser($instance);
    return $packer->pack($instance_parser->tags_values);
  }
}
