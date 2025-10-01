<?php
namespace Blackprint;

class InitUpdate {
	const NoRouteIn = 2; // Only when no input cable connected
	const NoInputCable = 4; // Only when no input cable connected
	const WhenCreatingNode = 8; // When all the cable haven't been connected (other flags may be affected)
}