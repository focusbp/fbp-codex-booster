<?php
require_once __DIR__.'/../../../lib/ExcelFormula.php';
require_once __DIR__.'/../../../lib/ExcelGrid.php';
$count=0;
function check($actual,$expected): void { global $count; if ($actual!==$expected) throw new RuntimeException(var_export([$actual,$expected],true)); $count++; }
function rejects(callable $fn): void { global $count; try { $fn(); } catch (Throwable $e) { $count++; return; } throw new RuntimeException('Expected failure'); }
function evaluate(string $expr,array $row=[]): ?float { return ExcelFormula::evaluate((new ExcelFormula)->parse($expr)['tree'],$row); }
check(evaluate('(A + B) * 1.1',['a'=>10,'b'=>20]),33.0);
check(evaluate('A + B * 2',['a'=>10,'b'=>20]),50.0);
check(evaluate('-(A - 2)',['a'=>5]),-3.0);
check(evaluate('A * 0',['a'=>'']),null);
check(evaluate('A * 2',['a'=>0]),0.0);
foreach (['phpinfo()','A ** 2','A +','(A + B','AZZ + 1','1;exit','1 / 0'] as $x) rejects(fn()=>evaluate($x));
$c=['a'=>['generated'=>true,'mode'=>'input','type'=>'float'], 'b'=>['generated'=>true,'mode'=>'calculation','type'=>'float','formula'=>'A * 1.25','digits'=>1,'rounding'=>'round'], 'c'=>['generated'=>true,'mode'=>'calculation','type'=>'float','formula'=>'B * 2','digits'=>1,'rounding'=>'round']];
check(ExcelFormula::calculate($c,['a'=>-1])['row']['b'],-1.3);
check(ExcelFormula::calculate($c,['a'=>-1])['row']['c'],-2.6);
$c['b']['rounding']='truncate';check(ExcelFormula::calculate($c,['a'=>-1])['row']['b'],-1.2);
$c['b']['rounding']='away';check(ExcelFormula::calculate($c,['a'=>-1])['row']['b'],-1.3);
$c['b']['formula']='A / 0';check(count(ExcelFormula::calculate($c,['a'=>1])['errors']),2);
$c['b']['formula']='C + 1';rejects(fn()=>ExcelFormula::plan($c));
$c['b']['formula']='A + 1';$c['a']['type']='text';rejects(fn()=>ExcelFormula::plan($c));
$c['a']['type']='float';$c['a']['mode']='function';rejects(fn()=>ExcelFormula::plan($c));
check(array_slice(ExcelGrid::orderedNames(['column_order'=>['b','a']]),0,3),['b','a','c']);
check(count(ExcelGrid::orderedNames(['column_order'=>['b','b','invalid']])),52);
ExcelGrid::validateOrder(ExcelGrid::names());
rejects(fn()=>ExcelGrid::validateOrder(['a','a']));
echo "PASS $count formula/order checks\n";
