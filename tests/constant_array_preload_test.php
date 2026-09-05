<?php
interface Controller {}
function startsWith($value, $prefix) { return str_starts_with((string) $value, (string) $prefix); }
putenv('FBP_FFM_LOG_DISABLE=1');
require __DIR__ . '/../fbp/lib/fixed_file_manager/fixed_file_manager.php';
require __DIR__ . '/../fbp/lib/Controller_class.php';
class ConstantPreloadDb extends fixed_file_manager {
    public $scans = 0, $selects = 0;
    function getall($sortitem = null, $sort_order = SORT_ASC) { $this->scans++; return parent::getall($sortitem, $sort_order); }
    function select($itemname, $value, $match_patterns = true, $and_or = 'AND', $sortitem = null, $sort_order = SORT_DESC, $max = null, &$is_last = null) {
        $this->selects++; return parent::select($itemname, $value, $match_patterns, $and_or, $sortitem, $sort_order, $max, $is_last);
    }
}
class ConstantPreloadController extends Controller_class {
    public $dynamic = ['1'=>'Live row'];
    function __construct(public $definitions, public $values) {
        $this->smarty = new class {
            public $vars = [], $assignments = [];
            function assign($key, $value) { $this->vars[$key] = $value; $this->assignments[] = [$key, $value]; }
        };
    }
    function db($name, ?string $class = null, ?string $separated_by = null): FFM {
        if ($name === 'constant_array') return $this->definitions;
        if ($name === 'values') return $this->values;
        throw new RuntimeException('Unexpected table');
    }
    function get_constant_array($name, $empty = false) {
        if (startsWith($name, 'table/')) return $this->dynamic;
        return parent::get_constant_array($name, $empty);
    }
}
function cp_assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function cp_snapshot($ctl, $bulk) {
    $ctl->smarty->vars = []; $ctl->smarty->assignments = [];
    if ($bulk) $ctl->assign_all_constant_arrays();
    else {
        $names = $ctl->get_all_constant_array_names(false, false);
        $ctl->smarty->assign('constant_array_name', $names);
        foreach ($names as $name) {
            $ctl->smarty->assign($name, $ctl->get_constant_array($name, false));
            $ctl->smarty->assign($name . '_colors', $ctl->get_constant_array_color($name));
        }
    }
    return [$ctl->smarty->vars, $ctl->smarty->assignments];
}
$root = __DIR__ . '/tmp-constant-preload-' . bin2hex(random_bytes(4));
mkdir($root . '/data', 0777, true); mkdir($root . '/fmt', 0777, true);
copy(__DIR__ . '/../fbp/app/constant_array/fmt/constant_array.fmt', $root . '/fmt/constant_array.fmt');
copy(__DIR__ . '/../fbp/app/constant_array/fmt/values.fmt', $root . '/fmt/values.fmt');
$a = $v = null;
try {
    $a = new ConstantPreloadDb('constant_array', $root . '/data', $root . '/fmt');
    $v = new ConstantPreloadDb('values', $root . '/data', $root . '/fmt');
    $ctl = new ConstantPreloadController($a, $v);
    cp_assert(cp_snapshot($ctl, false) === cp_snapshot($ctl, true), 'Empty definitions differ');
    $ids = [];
    foreach (['plain','table_fields','empty','duplicate','duplicate','plain_colors','table/customer/name','0','01',"\todd\t"] as $name) {
        $row = ['array_name'=>$name]; $ids[] = $a->insert($row);
    }
    $fixtures = [
        [$ids[0],'0','Zero','#111',2], [$ids[0],'01','Leading zero','',1],
        [$ids[0],'text','Text','#222',1], [$ids[0],'text','Overwrite without color','',3],
        [$ids[0],'blank','Blank','0',4], [$ids[1],'field','Field','#333',0],
        [$ids[3],'old','Old duplicate','#444',0], [$ids[4],'new','Newest duplicate','#555',0],
        [$ids[5],'collision','Collision','',0], [$ids[7],'key','Numeric name','',0],
        [$ids[8],'key','Leading-zero name','',0], [$ids[9],'key','Whitespace name','',0],
        [999,'orphan','Orphan','#777',0],
    ];
    foreach ($fixtures as [$id,$key,$value,$color,$sort]) {
        $row = ['constant_array_id'=>$id,'key'=>$key,'value'=>$value,'color'=>$color,'sort'=>$sort]; $v->insert($row);
    }
    $expected = cp_snapshot($ctl, false);
    $a->scans=$v->scans=$a->selects=$v->selects=0;
    cp_assert(cp_snapshot($ctl, true) === $expected, 'Legacy values, colors, ordering or assignment collisions differ');
    cp_assert($a->scans===1 && $v->scans===1 && $a->selects===0 && $v->selects===0, 'Bulk preload must scan each table once');
    $row = $v->get(1); $row['value']='Changed'; $v->update($row); $ctl->dynamic=['1'=>'Changed live row'];
    cp_assert(cp_snapshot($ctl, true) === cp_snapshot($ctl, false), 'New snapshot must reflect updates, including dynamic references');
    // Representative 54-set / 331-value fixture, compared using the same real FFM storage.
    $a->allclear(); $v->allclear();
    for ($i=0;$i<54;$i++) {
        $row=['array_name'=>'option_'.$i]; $id=$a->insert($row);
        for ($j=0;$j<6+($i<7?1:0);$j++) {
            $row=['constant_array_id'=>$id,'key'=>(string)$j,'value'=>'Value '.$j,'color'=>$j%2?'#123':'','sort'=>$j]; $v->insert($row);
        }
    }
    $timings=[];
    foreach ([false,true,false,true,false,true] as $bulk) {
        $start=microtime(true); $out=cp_snapshot($ctl,$bulk); $timings[$bulk?'bulk':'legacy'][]=round((microtime(true)-$start)*1000,3);
        if (!$bulk) $expected=$out; else cp_assert($out===$expected, 'Representative output changed');
    }
    echo json_encode(['ok'=>true,'cases'=>'empty, sort ties, duplicate keys/names, colors, table_fields, assignment collisions, numeric/text keys, dynamic references, fresh snapshots','timingsMs'=>$timings]),"\n";
} finally {
    if ($a) $a->close(); if ($v) $v->close();
    $remove=function($dir) use (&$remove) { foreach (array_diff(scandir($dir),['.','..']) as $name) { $p=$dir.'/'.$name;is_dir($p)?$remove($p):unlink($p); } rmdir($dir); };
    $remove($root);
}
