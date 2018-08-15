<?php

	require 'Store.php';

	if( empty( $argv[1] ) ) {
		die('Send me an argv' . PHP_EOL);
	}
	$result = Store::getDataStore($argv[1]);

	print_r( $result );