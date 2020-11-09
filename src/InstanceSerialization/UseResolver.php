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
use SplFileObject;

class UseResolver {
  /**@var ReflectionClass|null */
  private $instance_reflection = null;

  /**@var string[] */
  private $alias_to_name = [];

  /**@var string */
  private $cur_namespace = '';

  /**@var mixed[] */
  private $tokens = [];

  /**@var int */
  private $token_id = -1;

  /**
   * UseResolver constructor.
   * @throws ReflectionException
   */
  public function __construct(ReflectionClass $instance_reflection) {
    $this->instance_reflection = $instance_reflection;

    $content = '';
    $file    = new SplFileObject($this->instance_reflection->getFileName());
    for ($line_id = 0; !$file->eof() && $line_id < $this->instance_reflection->getStartLine(); $line_id++) {
      $content .= $file->fgets();
    }

    $this->findUses($content);
  }

  private function findUses(string $file_content): void {
    $this->tokens = token_get_all($file_content);

    /**
     * use My\Full\Classname as Another;
     * use My\Full\Classname as Another, My\Full\NSname;
     * use My\Full\NSname;
     * use \My\Fulle\Name;
     */
    while ($this->getNextToken()) {
      if ($this->isToken([T_NAMESPACE])) {
        $this->parseNamespace();
      } elseif ($this->isToken([T_USE])) {
        while (true) {
          [$alias, $class] = $this->parseUseStatement();
          if ($alias === null) {
            break;
          }
          $this->alias_to_name[$alias] = $class;
        }
      }
    }
  }

  private function getNextToken() {
    while ($this->token_id + 1 < count($this->tokens)) {
      $this->token_id++;
      if (!$this->isToken([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
        return $this->curToken();
      }
    }
    return null;
  }

  private function isToken(array $tokens): bool {
    return in_array($this->curToken()[0], $tokens, true);
  }

  private function curToken() {
    return $this->tokens[$this->token_id];
  }

  private function parseNamespace(): void {
    [, $this->cur_namespace] = $this->parseClassName();
  }

  private function parseClassName(): array {
    $class_name = '';
    $alias      = '';
    while ($this->getNextToken() && $this->isToken([T_STRING, T_NS_SEPARATOR])) {
      $class_name .= $this->curToken()[1];
      $alias      = $this->curToken()[1];
    }
    return [$alias, $class_name];
  }

  private function parseUseStatement(): ?array {
    if ($this->curToken() === ';' || $this->curToken() === null) {
      return null;
    }

    [$alias, $class_name] = $this->parseClassName();
    if ($this->isToken([T_AS])) {
      [, $alias] = $this->parseClassName();
    }

    return [$alias, $class_name];
  }

  public function resolveName(string $instance_name): string {
    if ($instance_name[0] === '\\') {
      return $instance_name;
    }

    if ($instance_name === 'self') {
      return $this->instance_reflection->getName();
    }

    $backslash_position = strstr($instance_name, '\\') ?: strlen($instance_name);
    $first_part_of_name = substr($instance_name, 0, $backslash_position);
    if (isset($this->alias_to_name[$first_part_of_name])) {
      return $this->alias_to_name[$first_part_of_name] . substr($instance_name, strlen($first_part_of_name));
    }

    return $this->instance_reflection->getNamespaceName() . '\\' . $instance_name;
  }
}
