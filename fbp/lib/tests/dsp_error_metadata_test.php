<?php
if(PHP_SAPI!=='cli')exit(2);
interface Controller {}
require_once dirname(__DIR__).'/DspRuntime.php';
final class MetadataController implements Controller {
    private bool $guard=false;
    public function get_prohibit_new_db(){return $this->guard;}
    public function set_prohibit_new_db($v){$this->guard=$v;}
    public function get_dsp_system_subject(){return ['verified'=>true,'kind'=>'cron','job_id'=>1];}
}
$checks=0;
function verifyMetadata($v){global $checks;if(!$v)throw new RuntimeException('Metadata assertion failed');$checks++;}
$e=new DspException('owner','cross_owner_access',['expected_denial'=>true,'password'=>'secret-value','row'=>['email'=>'private@example.test']]);
$meta=$e->getServerErrorMetadata('public');verifyMetadata($meta['expected_denial']===true);verifyMetadata($meta['reason']==='cross_owner_access');
verifyMetadata(!str_contains(json_encode($meta),'secret-value'));verifyMetadata(!str_contains(json_encode($meta),'private@example.test'));
$e=new DspException('invalid rule with spaces','token-with-secret@value',['expected_denial'=>'true']);$meta=$e->getServerErrorMetadata('untrusted');
verifyMetadata($meta['rule']===''&&$meta['reason']==='');verifyMetadata(!$meta['expected_denial']&&$meta['channel']==='unknown');
$ctl=new MetadataController;
try{DspRuntime::invoke($ctl,'get',['channel'=>'cron'],fn()=>throw new DspException('cleanup','operation_denied'));}catch(DspException $e){$meta=$e->getServerErrorMetadata();}
verifyMetadata($meta['channel']==='cron'&&$meta['operation']==='get'&&$meta['trusted_cron']===true);verifyMetadata(!$ctl->get_prohibit_new_db());
try{DspRuntime::invoke($ctl,'get',['channel'=>'public'],fn()=>throw new RuntimeException('private error text'));}catch(DspException $e){$meta=$e->getServerErrorMetadata();}
verifyMetadata($meta['reason']==='policy_evaluation_error');verifyMetadata(!str_contains(json_encode($meta),'private error text'));
echo json_encode(['ok'=>true,'checks'=>$checks])."\n";
