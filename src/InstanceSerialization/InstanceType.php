<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\InstanceSerialization;

use ReflectionClass;
use ReflectionException;
use RuntimeException;

class InstanceType extends PHPDocType {
  /**@var string */
  public $type = '';

  protected static function parseImpl(string &$str): ?PHPDocType {
    if (preg_match('/^(\\\\?[A-Z][a-zA-Z0-9_\x80-\xff\\\\]*|self|static|object)/', $str, $matches)) {
      $res       = new self();
      $res->type = $matches[1];
      $str       = substr($str, strlen($res->type));

      if (in_array($res->type, ['static', 'object'], true)) {
        throw new RuntimeException('static|object are forbidden in phpdoc');
      }
      return $res;
    }

    return null;
  }

  /**
   * @param ?array $value
   * @return ?object
   * @throws ReflectionException
   * @throws RuntimeException
   */
  public function fromUnpackedValue($value, UseResolver $use_resolver) {
    $resolved_class_name = $this->getResolvedClassName($use_resolver);
    $parser              = new InstanceDeserializer($resolved_class_name);
    return $parser->fromUnpackedArray($value);
  }

  private function getResolvedClassName(UseResolver $use_resolver): string {
    $resolved_class_name = $use_resolver->resolveName($this->type);
    if (!class_exists($resolved_class_name)) {
      throw new RuntimeException("Can't find class: {$resolved_class_name}");
    }
    return $resolved_class_name;
  }

  /**
   * @param mixed       $value
   * @throws ReflectionException
   */
  public function verifyValue($value, UseResolver $use_resolver): void {
    if ($value === null) {
      return;
    }

    if (!is_object($value)) {
      self::throwRuntimeException($value, $this->type);
    }

    $resolved_name = $this->getResolvedClassName($use_resolver);
    $rc            = new ReflectionClass($resolved_name);
    $parser        = new InstanceSerializer($value); // will verify values inside $value
    if ($parser->instance_metadata->reflection_of_instance->getName() !== $rc->getName()) {
      self::throwRuntimeException($rc->getName(), $this->type);
    }
  }

  protected function hasInstanceInside(): bool {
    return true;
  }
}

