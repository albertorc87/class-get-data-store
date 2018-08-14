<?php

	require 'Store.php';
	$result = Store::getDataStore($argv[1]);

	print_r( $result );