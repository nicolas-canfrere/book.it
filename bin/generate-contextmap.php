#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Tools\ContextMap\ContractScanner;
use Tools\ContextMap\ContextMapBuilder;
use Tools\ContextMap\DeptracRulesetParser;
use Tools\ContextMap\MermaidWriter;
use Tools\ContextMap\YamlWriter;

$root     = dirname(__DIR__);
$contracts = (new ContractScanner())->scan($root . '/src');
$consumes  = (new DeptracRulesetParser())->parse($root . '/deptrac-contexts.yaml');
$map       = (new ContextMapBuilder())->build($contracts, $consumes);

(new YamlWriter())->write($map, $root . '/contextmap.yaml');
(new MermaidWriter())->write($map, $root . '/docs/context-map.md');

echo "Generated contextmap.yaml and docs/context-map.md\n";
