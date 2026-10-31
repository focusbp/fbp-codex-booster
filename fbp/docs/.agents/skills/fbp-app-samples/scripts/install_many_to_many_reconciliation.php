<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
array_splice($argv,1,0,['many-to-many-reconciliation']);
require __DIR__.'/install_workflow_sample.php';
