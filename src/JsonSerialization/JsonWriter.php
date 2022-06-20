<?php
// Compiler for PHP (aka KPHP) polyfills
// Copyright (c) 2020 LLC «V Kontakte»
// Distributed under the GPL v3 License, see LICENSE.notice.txt

/** @noinspection NoTypeDeclarationInspection */
/** @noinspection KphpReturnTypeMismatchInspection */
/** @noinspection KphpParameterTypeMismatchInspection */

namespace KPHP\JsonSerialization;

class JsonWriter {
  private string $buf = '';
  private int $float_precision = 0;
  private bool $pretty_print;
  private bool $preserve_zero_fraction;

  private int $indent_ = 0;
  private bool $has_root_ = false;
  private array $stack_ = [];
  private int $stack_top_ = -1;
  private array $precision_stack_ = [];


  function __construct(bool $pretty_print, bool $preserve_zero_fraction) {
    $this->pretty_print = $pretty_print;
    $this->preserve_zero_fraction = $preserve_zero_fraction;
  }


  function Bool(bool $b) {
    $this->RegisterValue();
    $this->buf .= ($b ? "true" : "false");
  }

  function Int(int $i) {
    $this->RegisterValue();
    $this->buf .= $i;
  }

  function Double(float $d) {
    $this->RegisterValue();
    if (is_nan($d) || is_infinite($d)) {
      $d = 0.0;
    }
    if ($this->float_precision) {
      $this->buf .= round($d, $this->float_precision);
    } else {
      $this->buf .= $d;
    }
    if ($this->preserve_zero_fraction) {
      if ($d === (float)(int)$d) {
        $this->buf .= '.0';
      }
    }
  }

  function String(string $s) {
    $this->RegisterValue();
    $this->buf .= '"';
    self::escape_json_string($this->buf, $s);
    $this->buf .= '"';
  }

  function RawString(string $s) {
    $this->RegisterValue();
    $this->buf .= $s;
  }

  function Null() {
    $this->RegisterValue();
    $this->buf .= 'null';
  }

  function Key(string $key, bool $escape = false) {
    if ($this->stack_top_ === -1 || $this->stack_[$this->stack_top_]['in_array']) {
      throw new KphpJsonEncodeException("json key is allowed only inside object");
    }
    if ($this->stack_[$this->stack_top_]['values_count']) {
      $this->buf .= ',';
    }
    if ($this->pretty_print) {
      $this->buf .= "\n";
      $this->WriteIndent();
    }
    $this->buf .= '"';
    if ($escape) {
      self::escape_json_string($this->buf, $key);
    } else {
      $this->buf .= $key;
    }
    $this->buf .= '"';
    $this->buf .= ':';
    if ($this->pretty_print) {
      $this->buf .= ' ';
    }
  }

  function StartObject() {
    $this->NewLevel(false);
  }

  function EndObject() {
    $this->ExitLevel(false);
  }

  function StartArray() {
    $this->NewLevel(true);
  }

  function EndArray() {
    $this->ExitLevel(true);
  }

  function IsComplete(): bool {
    return $this->stack_top_ == -1 && $this->has_root_;
  }

  function GetJson(): string {
    return $this->buf;
  }

  function SetFloatPrecision(int $float_precision) {
    if ($this->float_precision) {
      $this->precision_stack_[] = $this->float_precision;
    }
    $this->float_precision = $float_precision;
  }

  function RestoreFloatPrecision() {
    $this->float_precision = count($this->precision_stack_) ? array_pop($this->precision_stack_) : 0;
  }

  private function RegisterValue() {
    if ($this->has_root_ && $this->stack_top_ === -1) {
      throw new KphpJsonEncodeException("attempt to set value twice in a root of json");
    }
    if ($this->stack_top_ === -1) {
      $this->has_root_ = true;
      return;
    }
    if ($this->stack_[$this->stack_top_]['in_array']) {
      if ($this->stack_[$this->stack_top_]['values_count']) {
        $this->buf .= ',';
      }
      if ($this->pretty_print) {
        $this->buf .= "\n";
        $this->WriteIndent();
      }
    }
    $this->stack_[$this->stack_top_]['values_count']++;
  }

  private function WriteIndent() {
    if ($this->indent_) {
      $this->buf .= str_repeat(' ', $this->indent_);
    }
  }

  private function NewLevel(bool $is_array) {
    $this->RegisterValue();
    $this->stack_top_++;
    $this->stack_[$this->stack_top_] = ['in_array' => $is_array, 'values_count' => 0];
    $this->buf .= ($is_array ? '[' : '{');
    $this->indent_ += 4;
  }

  private function ExitLevel(bool $is_array) {
    if ($this->stack_top_ === -1) {
      throw new KphpJsonEncodeException("brace disbalance");
    }
    $cur_level = $this->stack_[$this->stack_top_];
    $this->stack_top_--;
    if ($cur_level['in_array'] !== $is_array) {
      throw new KphpJsonEncodeException("attempt to enclosure " . ($cur_level['in_array'] ? '[' : '{') . " with " . ($is_array ? ']' : '}'));
    }
    $this->indent_ -= 4;
    if ($this->pretty_print && $cur_level['values_count']) {
      $this->buf .= "\n";
      $this->WriteIndent();
    }
    $this->buf .= $is_array ? ']' : '}';
  }

  static private function escape_json_string(string &$buf, string $v) {
    $len = strlen($v);
    for ($i = 0; $i < $len; ++$i) {
      $c = $v[$i];
      switch ($c) {
        case '"':
          $buf .= '\\"';
          break;
        case '\\':
          $buf .= '\\\\';
          break;
        case '/':
          $buf .= '\\/';
          break;
        case "\f";
          $buf .= '\\f';
          break;
        case "\n";
          $buf .= '\\n';
          break;
        case "\r";
          $buf .= '\\r';
          break;
        case "\t";
          $buf .= '\\t';
          break;
        default:
          $buf .= $c;
      }
    }
  }
}
