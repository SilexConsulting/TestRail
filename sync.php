<?php

/**
 * Accepts one param, an ini file.
 */

require_once 'TestRailSync.php';

set_exception_handler('exception_handler');
main($argv);

/**
 * Perform sync operation
 *
 * @param array $argv
 */
function main($argv)
{
	if (sizeof($argv) != 2) {
		throw new Exception('usage: php sync.php <path_to_config_file>');
	}

	$config = get_config($argv[1]);

	$testRail = new TestRailSync($config['url']);
	$testRail->set_user($config['username']);
	$testRail->set_password($config['password']);
	$testRail->set_source($config['source']);
	$testRail->set_destination($config['destination']);

	$testRail->sync();
}

/**
 * Parse an ini file into an associative array
 *
 * @param string $filename
 * The filename of the ini file to be parsed
 * @throws Exception
 * @return array The settings are returned as an associative array on success, else FALSE on failure
 */
function get_config($filename)
{
	$config = parse_ini_file($filename);

	if ($config === FALSE) {
		throw new Exception("could not read configuration from '{$filename}'");
	}

	if (!isset($config['url'])) {
		throw new Exception("url not found in '{$filename}'");
	}
	if (!isset($config['username'])) {
		throw new Exception("username not found in '{$filename}'");
	}
	if (!isset($config['password'])) {
		throw new Exception("password not found in '{$filename}'");
	}
	if (!isset($config['source'])) {
		throw new Exception("source project not found in '{$filename}'");
	}
	if (!isset($config['destination'])) {
		throw new Exception("destination project not found in '{$filename}'");
	}

	return $config;
}

/**
 * Exception-handler of last resort, writes to STDERR
 *
 * @param Exception $exception
 */
function exception_handler(Exception $exception)
{
	fwrite(STDERR, $exception->getMessage() . " on line " . $exception->getLine() . " in file " . $exception->getFile() . PHP_EOL);

	var_dump($exception->getTrace());
}
