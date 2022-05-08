<?php

#ifndef KPHP

function typeof($arg)
{
	return get_class($arg);
}


function kphp_polyfill_getDeclaredProps($v, array &$errors):array
{
	$errors = [];
	$refClass = new ReflectionClass($v);
	$declaredProps = [];
	foreach ( $refClass->getProperties(ReflectionProperty::IS_PUBLIC|ReflectionProperty::IS_PROTECTED|ReflectionProperty::IS_PRIVATE) as $prop ) {
		$declaredProps[$prop->name] = 1;
	};
	return $declaredProps;
}
function kphp_polyfill_checkPropExists(?array &$declaredProps, string $propName, $value, array &$errors)
{
	if (!isset($declaredProps[$propName])) {
		$type = gettype($value);
		$errors [] = "public $type \$$propName = null;";
	}
}
function kphp_polyfill_verifyProps($v, array $errors)
{
	if  (count($errors)) {
		throw new Exception("Fields not declared as property in ".get_class($v) ."\n".implode("\n", $errors));
	}
}

function kphp_polyfill_checkInstance(?object $instance, string $class_name)
{
	if  ($instance && ! ($instance instanceof $class_name)) {
		throw new Exception("Can't cast instance of ".get_class($instance) ." to class $class_name.");
	}
}
#endif

