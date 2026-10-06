<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../NotFoundResponse.php';
$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$config = ['not_found_class' => 'public_pages', 'not_found_function' => 'not_found'];
$fallback = ['text/plain; charset=UTF-8', 'Not Found'];
$render = function () { echo '<main>Missing page</main>'; };
check(NotFoundResponse::body($config, $render, true) === ['text/html; charset=UTF-8', '<main>Missing page</main>'], 'HTML rendering');
check(NotFoundResponse::body([], $render, true) === $fallback, 'unset');
check(NotFoundResponse::body(['not_found_class'=>'../base','not_found_function'=>'page'], $render, true) === $fallback, 'invalid class');
check(NotFoundResponse::body(['not_found_class'=>'base','not_found_function'=>'__construct'], $render, true) === $fallback, 'magic method');
check(NotFoundResponse::body($config, $render, false) === $fallback, 'non HTML');
check(NotFoundResponse::body($config, function () {}, true) === $fallback, 'empty rendering');
check(NotFoundResponse::body($config, function () { echo 'partial private detail'; throw new Exception('secret'); }, true) === $fallback, 'exception discards partial output');
check(NotFoundResponse::body($config, function () use ($config, $render) { NotFoundResponse::body($config, $render, true); }, true) === $fallback, 'recursion');
check(NotFoundResponse::body($config, $render, true)[0] === 'text/html; charset=UTF-8', 'guard resets');
foreach ([['REQUEST_METHOD'=>'POST'], ['HTTP_ACCEPT'=>'application/json'], ['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']] as $server) {
    check(!NotFoundResponse::acceptsHtml($server, [], 'public_pages'), 'request exclusion');
}
check(!NotFoundResponse::acceptsHtml([], ['_call_from'=>'appcon'], 'public_pages'), 'appcon exclusion');
check(!NotFoundResponse::acceptsHtml([], [], 'db_api'), 'API exclusion');
check(!NotFoundResponse::acceptsHtml([], [], 'mcp_server'), 'MCP exclusion');
check(NotFoundResponse::acceptsHtml(['REQUEST_METHOD'=>'HEAD'], [], 'public_pages'), 'HEAD');
echo "NotFoundResponse: {$checks} checks passed\n";
