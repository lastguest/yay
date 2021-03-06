<?php declare(strict_types=1);

use Yay\{ TokenStream, Ast, Directives, Macro, Pattern, Expansion, Cycle, Map, BlueContext };

use function Yay\{
    token, rtoken, any, operator, optional, commit, chain, braces,
    consume, lookahead, repeat, traverse, midrule
};

use const Yay\{ CONSUME_DO_TRIM };

function yay_parse(string $source, Directives $directives = null, BlueContext $blueContext = null) : string {

    if ($gc = gc_enabled()) gc_disable(); // important optimization!

    static $globalDirectives = null;

    if (null === $globalDirectives) $globalDirectives = new ArrayObject;

    $directives = $directives ?: new Directives;
    $blueContext = $blueContext ?: new BlueContext;

    $cg = (object) [
        'ts' => TokenStream::fromSource($source),
        'directives' => $directives,
        'cycle' => new Cycle($source),
        'globalDirectives' => $globalDirectives,
        'blueContext' => $blueContext,
    ];

    foreach($cg->globalDirectives as $d) $cg->directives->add($d);

    traverse
    (
        // this midrule is where the preprocessor really does the job!
        midrule(function(TokenStream $ts) use ($directives, $blueContext) {
            $token = $ts->current();

            tail_call: {
                if (null === $token) return;

                // skip when something looks like a new macro to be parsed
                if ('macro' === (string) $token) return;

                // here we do the 'magic' to match and expand userland macros
                $directives->apply($ts, $token, $blueContext);

                $token = $ts->next();

                goto tail_call;
            }
        })
        ,
        // here we parse, compile and allocate new macros
        consume
        (
            chain
            (
                token(T_STRING, 'macro')->as('declaration')
                ,
                optional
                (
                    repeat
                    (
                        rtoken('/^·\w+$/')
                    )
                )
                ->as('tags')
                ,
                lookahead
                (
                    token('{')
                )
                ,
                commit
                (
                    chain
                    (
                        braces()->as('pattern')
                        ,
                        operator('>>')
                        ,
                        braces()->as('expansion')
                    )
                )
                ->as('body')
                ,
                optional
                (
                    token(';')
                )
            )
            ,
            CONSUME_DO_TRIM
        )
        ->onCommit(function(Ast $macroAst) use ($cg) {
            $scope = Map::fromEmpty();
            $tags = Map::fromValues(array_map('strval', $macroAst->{'tags'}));
            $pattern = new Pattern($macroAst->{'declaration'}->line(), $macroAst->{'body pattern'}, $tags, $scope);
            $expansion = new Expansion($macroAst->{'body expansion'}, $tags, $scope);

            $macro = new Macro($tags, $pattern, $expansion, $cg->cycle);

            $cg->directives->add($macro); // allocate the userland macro

            // allocate the userland macro globally if it's declared as global
            if ($macro->tags()->contains('·global')) $cg->globalDirectives[] = $macro;
        })
    )
    ->parse($cg->ts);

    $expansion = (string) $cg->ts;

    if ($gc) gc_enable();

    return $expansion;
}
