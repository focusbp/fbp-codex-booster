<?php
if (PHP_SAPI !== 'cli') exit(2);
interface Controller {}
require_once dirname(__DIR__) . '/DspRuntime.php';
require_once dirname(__DIR__) . '/DspPolicyTemplate.php';
final class TemplateController implements Controller {
    public string $entry = 'db_api';
    public ?array $subject = ['verified'=>true,'kind'=>'api'];
    public function get_classname(): string { return $this->entry; }
    public function get_dsp_system_subject(): ?array { return $this->subject; }
}
$ctl = new TemplateController;
$checks = 0;
function verifyTemplate(bool $value): void { global $checks; if (!$value) throw new RuntimeException('DSP template assertion failed'); $checks++; }
foreach (['allow','deny','custom'] as $mode) {
    $name='Generated'.ucfirst($mode).'Policy';
    $output=DspPolicyTemplate::generate($name,array_fill_keys(['add','read','update','delete'],['mode'=>$mode,'conditions'=>'confirmed test rule']));
    eval(substr($output['php'],5));
    $operations=[
        fn($policy)=>$policy->authorizeInsert(['owner'=>9,'secret'=>'value']),
        fn($policy)=>$policy->authorizeRead(['itemname'=>[['secret']],'sortitem'=>'secret']),
        fn($policy)=>$policy->inspectRead(['owner'=>9,'secret'=>'value'])->project(['owner'=>9,'secret'=>'value']),
        fn($policy)=>$policy->authorizeUpdate(['owner'=>1],['owner'=>9],['owner','secret']),
        fn($policy)=>$policy->authorizeDelete(['owner'=>9]),
    ];
    foreach ($operations as $i=>$operation) {
        $result=$operation(new $name($ctl,'api'));
        verifyTemplate($i !== 2 || $result===['owner'=>9,'secret'=>'value']);
    }
    foreach ([['api','db_api',null],['api','other_api',['verified'=>true,'kind'=>'api']],['public','db_api',['verified'=>true,'kind'=>'api']],['api','db_api',['verified'=>1,'kind'=>'api']],['api','db_api',['verified'=>true,'kind'=>'cron']]] as [$channel,$entry,$subject]) {
        $ctl->entry=$entry; $ctl->subject=$subject;
        foreach ($operations as $operation) {
            $denied=false;
            try { $operation(new $name($ctl,$channel)); } catch (DspException $expected) { $denied=true; }
            verifyTemplate($denied===($mode!=='allow'));
        }
    }
    $ctl->entry='db_api'; $ctl->subject=['verified'=>true,'kind'=>'api'];
}
$output=DspPolicyTemplate::generate('UnconfiguredPolicy',[]); eval(substr($output['php'],5));
$ctl->subject=null; (new UnconfiguredPolicy($ctl,'public'))->authorizeInsert([]); verifyTemplate(true);
echo json_encode(['verified'=>true,'checks'=>$checks,'modes'=>['allow','deny','custom'],'management_exception'=>true])."\n";
