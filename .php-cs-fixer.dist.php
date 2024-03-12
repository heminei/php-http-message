<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('tmp')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;
