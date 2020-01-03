<?php 

require_once __DIR__ . '/db/Data/ObjectA.php';
require_once __DIR__ . '/db/Data/ObjectB.php';

use \Test\TestEnvironment\Data\ObjectA;
use \Test\TestEnvironment\Data\ObjectB;

// $obj = new ObjectA();
// $obj->setPar1(1);
// $obj->setPar2($obj->getPar1());
// $obj->setPar3('abc');
// $obj->setJson(['multi', [ 'level' => 'array']]);

// $obj->save();

// $foo = new ObjectB();
// $foo->setPar1(100 - $obj->getPar1());
// $foo->setPar2($foo->getPar1());
// $foo->setPar3('def');
// $foo->setJson(null);
// $foo->setParent($obj);
// $foo->setExt(true);

// $foo->save();

$obj = ObjectA::load(32);
$obj->delete();