<?php
/**
 * Contains core autoloading system
 */

namespace Gpws\Core;

/**
 * Autoload class
 *
 * On systems where the file name is case sensitive, 
 */
class Autoload {
	static public function load($class) {
printf('Load class: %s%s', $class, PHP_EOL);

		if (substr($class, 0, 5) !== 'Gpws\\') return false;

		$path = strtolower(str_replace(array('Gpws\\', '\\'), array(__DIR__ . '/', '/'), $class)) . '.php';

		include $path;
	}
}

spl_autoload_register(__NAMESPACE__ . '\\Autoload::load');
