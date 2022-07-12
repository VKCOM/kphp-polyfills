<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\PhpDocParsing;

use KPHP\JsonSerialization\JsonDeserializer;
use KPHP\JsonSerialization\KphpJsonDecodeException;
use KPHP\MsgPackSerialization\MsgPackDeserializer;
use KPHP\MsgPackSerialization\MsgPackSerializer;
use ReflectionClass;
use RuntimeException;

class InstanceType extends PhpDocType {
  public string $class_name;

  function __construct(string $class_name) {
    $this->class_name = $class_name;
  }

  protected static function parseImpl(string &$str, UseResolver $use_resolver): ?PhpDocType {
    if (!preg_match('/^(\\\\?[A-Z][a-zA-Z0-9_\x80-\xff\\\\]*|self|static|object)/', $str, $matches)) {
      return null;
    }

    $relative = $matches[1];
    $str = substr($str, strlen($relative));

    if ($relative === 'static' || $relative === 'object') {
      throw new RuntimeException('static|object are forbidden in phpdoc');
    }

    $class_name = $use_resolver->resolveName($relative);
    if (!class_exists($class_name) && !interface_exists($class_name)) {
      throw new RuntimeException("Can't find class: $class_name");
    }

    return new self($class_name);
  }

  public function fromUnpackedValue($value) {
    $parser = new MsgPackDeserializer($this->class_name);
    return $parser->fromUnpackedArray($value);
  }

  public function verifyValue($value): void {
    if ($value === null) {
      return;
    }

    if (!is_object($value)) {
      self::throwRuntimeException($value, $this->class_name);
    }

    $rc = new ReflectionClass($this->class_name);
    $parser = new MsgPackSerializer($value); // will verify values inside $value
    if ($parser->instance_metadata->klass->getName() !== $rc->getName()) {
      self::throwRuntimeException($rc->getName(), $this->class_name);
    }
  }

  protected function hasInstanceInside(): bool {
    return true;
  }

  public function isNullAllowed(): bool {
    return false; // note, that "A" doesn't allow null here, only "?A" does
  }

  public function fromJson(\KPHP\JsonSerialization\JsonPath $json_path, $v, string $json_encoder) {
    // here we shouldn't check for stdClass: $v could be anything due to flatten classes
    $sub_deserializer = new JsonDeserializer($this->class_name, $json_encoder);
    return $sub_deserializer->decodeAndReturnInstance($json_path, $v);
  }
}

