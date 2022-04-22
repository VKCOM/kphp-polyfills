<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocTypeParsing;

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
    $parser = new \KPHP\MsgPackSerialization\InstanceDeserializer($resolved_class_name);
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
   */
  private function checkObject($value): void {
    if ($value === null) {
      return;
    }

    if (!is_object($value)) {
      self::throwRuntimeException($value, $this->type);
    }
  }

  private function checkUseResolver(object $serializer, UseResolver $use_resolver): void {
    $resolved_name = $this->getResolvedClassName($use_resolver);
    $reflection = new ReflectionClass($resolved_name);
    if ($serializer->instance_metadata->reflection_of_instance->getName() !== $reflection->getName()) {
      self::throwRuntimeException($reflection->getName(), $this->type);
    }
  }

  /**
   * @param mixed       $value
   * @throws ReflectionException
   */
  public function verifyValue($value, UseResolver $use_resolver): void {
    if ($value === null) {
      return;
    }
    $this->checkObject($value);
    $parser = new \KPHP\MsgPackSerialization\InstanceSerializer($value); // will verify values inside $value
    $this->checkUseResolver($parser, $use_resolver);
  }

  protected function hasInstanceInside(): bool {
    return true;
  }

  protected function hasNullInside(): bool {
    return false;
  }

  protected function getDefaultValue() {
    return null;
  }

  public function storeValueToMap(string $name, $value, array &$map, string $encoder_name, UseResolver $use_resolver): void {
    if ($value === null) {
      $map[$name] = $this->getDefaultValue();
      return;
    }
    $this->checkObject($value);

    $map_obj = [];
    $serializer = new \KPHP\JsonSerialization\InstanceSerializer($value, $encoder_name);
    $serializer->encode($map_obj);
    #serialize empty object as '{}', not as '[]'
    $map[$name] = $map_obj ?: (object)[];

    $this->checkUseResolver($serializer, $use_resolver);
  }

  public function decodeValue($value, string $encoder_name, UseResolver $use_resolver) {
    if ($value === null) {
      return $this->getDefaultValue();
    }
    $resolved_class_name = $this->getResolvedClassName($use_resolver);
    $deserializer = new \KPHP\JsonSerialization\InstanceDeserializer($resolved_class_name, $encoder_name);
    return $deserializer->decode($value);
  }
}

