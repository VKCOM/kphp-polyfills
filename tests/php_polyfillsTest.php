<?php
declare(strict_types=1);

class TestValueInstance {
  public $value = 0;
  public function __construct ($value) { $this->value = $value; }
}

/**
 * @backupGlobals
 */
class php_polyfillsTest extends \PHPUnit\Framework\TestCase {
  public function test_instance_cache() {
    $val = new TestValueInstance(42);

    $this->assertNull(instance_cache_fetch(TestValueInstance::class, 'cache_key'), 'Error on fetching non existing key');

    $store_res = instance_cache_store('cache_key', $val);
    $this->assertTrue($store_res, 'Unexpected instance_cache_store result');

    $loaded_val = instance_cache_fetch(TestValueInstance::class, 'cache_key');
    $this->assertTrue($loaded_val instanceof TestValueInstance, 'Unexpected instance_cache_fetch return value type');
    $this->assertEquals($val, $loaded_val, 'Unexpected instance_cache_fetch return value');

    $del_res = instance_cache_delete('cache_key');
    $this->assertTrue($del_res, 'Unexpected instance_cache_delete result');
  }

  public function test_instance_to_array() {
    $this->assertEquals(['value' => 42],              instance_to_array(new TestValueInstance(42)));
    $this->assertEquals(['value' => ['value' => 42]], instance_to_array(new TestValueInstance(new TestValueInstance(42))));
  }
}
