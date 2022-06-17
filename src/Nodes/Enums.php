<?php
namespace Blackprint\Nodes;

enum Enums {
	case BPEnvGet;
	case BPEnvSet;
	case BPVarGet;
	case BPVarSet;
	case BPFnVarInput;
	case BPFnVarOutput;
	case BPFnInput;
	case BPFnOutput;
	case BPFnMain;
}