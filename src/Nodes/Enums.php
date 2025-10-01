<?php
namespace Blackprint\Nodes;

enum Enums {
	case BPEnvGet;
	case BPEnvSet;
	case BPVarGet;
	case BPVarSet;
	case BPFnVarInput;
	case BPFnVarOutput; // Not used, but reserved
	case BPFnInput;
	case BPFnOutput;
	case BPFnMain;
	case BPEventListen;
	case BPEventEmit;
}