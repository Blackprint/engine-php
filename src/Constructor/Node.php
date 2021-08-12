<?php
namespace Blackprint\Constructor;

class Node{
	/** @var array Port */
	public $output = [];
	public $input = [];
	public $property = [];

	public $init = false;
	public $request = false;
	public $update = false;

	/** @var NodeInterface */
	public $iface = false;
}