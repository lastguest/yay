--TEST--
Extra test for ·ls()
--FILE--
<?php

macro {
   ·ls(
        ·token(T_VARIABLE)·var
        ,
        ·token(':')
    )
   ·vars
} >> {
    match(·vars ···(, ){·var})
}

$a : $b : $c AND $x : $y : $z;

?>
--EXPECTF--
<?php

match($a, $b, $c) AND match($x, $y, $z);

?>
