<?php
namespace Blackprint\Constructor;

class Node{
	/** @var array Port */
	public $outputs = [];
	public $inputs = [];
	public $properties = [];

	public $init = false;
	public $request = false;
	public $update = false;

	/** @var NodeInterface */
	public $iface = false;
}