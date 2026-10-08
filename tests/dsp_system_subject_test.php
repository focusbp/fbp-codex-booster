<?php
/** Actual HMAC verification, entirely synthetic credentials and no DB/network calls. */
interface Controller {}
require_once (getenv('FBP_TEST_FRAMEWORK') ?: __DIR__.'/../fbp').'/lib/Controller_class.php';
class DspAuthTestController extends Controller_class {
    public bool $configurationError = false;
    function __construct() {}
    function generate_api_credentials() {
        if ($this->configurationError) throw new RuntimeException('Fixture failure');
        return ['api_key'=>'fixture-key','api_secret'=>'fixture-secret'];
    }
}
function check($v,$m) { if (!$v) throw new RuntimeException($m); }
function request($ctl,$sign = null) {
    $_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/fixture'; $_SERVER['QUERY_STRING']='';
    $_SERVER['HTTP_X_API_KEY']='fixture-key'; $_SERVER['HTTP_X_API_TS']=(string)time();
    $_SERVER['HTTP_X_API_SIGN']=$sign ?? hash_hmac('sha256',"GET\n/fixture\n\n".$_SERVER['HTTP_X_API_TS'],'fixture-secret');
    ob_start(); try { return $ctl->verify_api_request(); } finally { ob_end_clean(); }
}
$c=new DspAuthTestController(); check($c->get_dsp_system_subject()===null,'No subject before auth');
check(request($c)===true,'Valid HMAC'); check($c->get_dsp_system_subject()===['kind'=>'api','verified'=>true],'Verified API subject');
check(request($c,'invalid')===false,'Invalid HMAC'); check($c->get_dsp_system_subject()===null,'Failed verification revokes previous proof');
check(request($c)===true,'Authenticate again'); $c->configurationError=true;
try { request($c); throw new LogicException('Expected configuration failure'); } catch (RuntimeException $e) {}
check($c->get_dsp_system_subject()===null,'Configuration error clears proof');
echo "PASS: 8 API subject checks\n";
