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
use MessagePack\PackOptions;

class ClassTransformer implements CanPack {
  /** @var int */
  public static $depth = 0;

  /** @var int */
  public static $max_depth = 20;

  /**
   * @param object $instance
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function pack(Packer $packer, $instance): ?string {
    if ($instance instanceof DeepForceFloat32) {
      $packer = (new Packer(PackOptions::FORCE_STR | PackOptions::FORCE_FLOAT32))->extendWith(new ClassTransformer());
      return $packer->pack($instance->value);
    }
    $instance_parser = new InstanceSerializer($instance);
    return $packer->pack($instance_parser->tags_values);
  }
}
