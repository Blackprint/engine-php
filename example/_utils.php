<?php
namespace App;

function colorLog($category, $message = '') {
	$colors = [
		'reset' => "\033[0m",
		'red' => "\033[31m",
		'green' => "\033[32m",
		'yellow' => "\033[33m",
		'blue' => "\033[34m",
		'magenta' => "\033[35m",
		'cyan' => "\033[36m",
		'white' => "\033[37m",
	];

	$category_color = $colors['cyan'];
	if (strpos($category, 'Button') !== false) {
		$category_color = $colors['blue'];
	} elseif (strpos($category, 'Logger') !== false) {
		$category_color = $colors['green'];
	} elseif (strpos($category, 'Input') !== false) {
		$category_color = $colors['yellow'];
	} elseif (strpos($category, 'Math') !== false) {
		$category_color = $colors['magenta'];
	}

	echo $category_color . $category . $colors['reset'] . ' ' . $colors['white'] . $message . $colors['reset'] . PHP_EOL;
}