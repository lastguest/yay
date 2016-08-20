<?php declare(strict_types=1);

namespace Yay;

interface Directive {

    function id() : int;

    function specificity() : int;

    function pattern() : Parser;

    function apply(TokenStream $TokenStream, Directives $directives);
}
