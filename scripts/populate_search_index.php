#!/usr/bin/env php
<?php
/**
 * Utility script to populate the elastic search indexes
 *
 */

// Elastic search config
define('ES_URL', 'http://localhost:9200');
define('ES_INDEX', 'documentation');


function main($argv) {
	if (empty($argv[1])) {
		echo "A language to scan is required.\n";
		exit(1);
	}
	$lang = $argv[1];
	
	$directory = new RecursiveDirectoryIterator($lang);
	$recurser = new RecursiveIteratorIterator($directory);
	$matcher = new RegexIterator($recurser, '/\.rst/');

	foreach ($matcher as $file) {
		update_index($lang, $file);
	}
	echo "Index update complete\n";
}

function update_index($lang, $file) {
	$contents = file_get_contents($file);
	$filename = $file->getPathName();
	list($filename) = explode('.', $filename);

	$path = $filename . '.html';
	$id = str_replace($lang . '/', '', $filename);
	$id = str_replace('/', '-', $id);
	$id = trim($id, '-');

	$url = implode('/', array(ES_URL, ES_INDEX, $lang, $id));

	$data = array(
		'contents' => $contents,
		'title' => $filename,
		'url' => $path,
	);

	$data = json_encode($data);
	$size = strlen($data);

	$fh = fopen('php://memory', 'rw');
	fwrite($fh, $data);
	rewind($fh);

	echo "Sending request for $file to $url\n";

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_PUT, true);
	curl_setopt($ch, CURLOPT_INFILE, $fh);
	curl_setopt($ch, CURLOPT_INFILESIZE, $size);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$response = curl_exec($ch);
	$metadata = curl_getinfo($ch);

	if ($metadata['http_code'] > 400) {
		echo "Failed to complete request.\n";
		var_dump($response);
		exit(2);
	}

	curl_close($ch);
	fclose($fh);

	echo "Sent $file\n";
}

main($argv);
