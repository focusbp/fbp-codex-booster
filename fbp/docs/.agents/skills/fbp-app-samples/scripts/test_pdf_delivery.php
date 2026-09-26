<?php
// Contract tests with a mock Controller; browser/PDF integration is separate.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/assets/pdf-delivery/public_pages/public_pages.php';

class Controller {
    public $vars = [], $pdfText = [], $response = null;
    function set_check_login($value) {}
    function assign($key, $value) { $this->vars[$key] = $value; }
    function GET($key) { return $_GET[$key] ?? null; }
    function get_APP_URL($class, $function, $params) { return '/' . $class . '*' . $function . '?' . http_build_query($params); }
    function show_public_pages($template, ...$rest) { $this->response = $template; }
    function create_ValueFormatter() { return new class { function format_date($value) { return date('Y-m-d', $value); } }; }
    function create_pdfmaker() {
        $this->pdfText = [];
        return new class($this) {
            function __construct(private Controller $ctl) {}
            function setPageLayout($layout) {}
            function addText($text, $layout) { $this->ctl->pdfText[] = $text; }
            function download_pdf($filename) { $this->ctl->response = 'pdf'; }
        };
    }
}
class PdfFixture extends public_pages {
    public $subject = 'alice', $selected = 'a';
    public $documents = [
        'a' => ['id' => 'a', 'owner' => 'alice', 'issuable' => true, 'issued_at' => 1, 'amount' => 4000],
        'b' => ['id' => 'b', 'owner' => 'alice', 'issuable' => true, 'issued_at' => 1, 'amount' => 8000],
    ];
    protected function authenticated_subject(Controller $ctl): ?string { return $this->subject; }
    protected function load_document(Controller $ctl, ?string $id): ?array { return $this->documents[$id ?? $this->selected] ?? null; }
}
$checks = 0;
function check($value, $message) {
    global $checks;
    if (!$value) throw new RuntimeException($message);
    $checks++;
}
function issue($app, $ctl) {
    $app->protected_page($ctl);
    parse_str(parse_url($ctl->vars['download_url'], PHP_URL_QUERY), $query);
    return $query['code'];
}
function download($app, $ctl, $code) {
    $_GET = ['code' => $code]; $ctl->response = null; http_response_code(200);
    $app->protected_download($ctl);
    return $ctl->response;
}
function denied($app, $ctl, $code) {
    check(download($app, $ctl, $code) === 'document_error.tpl', 'must return HTML error, not PDF/JSON');
    check(http_response_code() === 403, 'denial must be HTTP 403');
}
$_SESSION = []; $ctl = new Controller(); $app = new PdfFixture($ctl);
$a = issue($app, $ctl); $app->selected = 'b'; $b = issue($app, $ctl);
check($a !== $b, 'pages must have independent grants');
$_SESSION['form_csrf'] = 'changed-by-another-page';
check(download($app, $ctl, $a) === 'pdf' && in_array('ご請求金額: 4,000円', $ctl->pdfText), 'old page must still download document A');
check(download($app, $ctl, $b) === 'pdf' && in_array('ご請求金額: 8,000円', $ctl->pdfText), 'new page must download document B');
check(download($app, $ctl, $a) === 'pdf', 'repeat retrieval must work');
denied($app, $ctl, 'invalid'); denied($app, $ctl, [$a]); denied($app, $ctl, str_repeat('0', 64));
$app->subject = 'bob'; denied($app, $ctl, $a);
$app->subject = null; denied($app, $ctl, $a);
$app->subject = 'alice'; $app->documents['a']['owner'] = 'bob'; denied($app, $ctl, $a);
$app->documents['a']['owner'] = 'alice'; $app->documents['a']['issuable'] = false; denied($app, $ctl, $a);
$app->documents['a']['issuable'] = true; $app->documents['a']['id'] = 'wrong'; denied($app, $ctl, $a);
$app->documents['a']['id'] = 'a';
$_SESSION['pdf_delivery_grants'][$a]['expires'] = time(); denied($app, $ctl, $a);
$_SESSION = []; denied($app, $ctl, $b);
$app->selected = 'b'; $fresh = issue($app, $ctl);
check(download($app, $ctl, $fresh) === 'pdf', 'verified re-entry must recover');
unset($app->documents['b']); denied($app, $ctl, $fresh);
$default = new public_pages($ctl); $default->protected_page($ctl);
check($ctl->response === 'document_error.tpl', 'unadapted hooks must deny access');
$session = []; $first = pdf_delivery_access::issue($session, 'alice', 'a', 100);
check(pdf_delivery_access::resolve($session, 'alice', $first, 999) === 'a', 'grant before expiry');
check(pdf_delivery_access::resolve($session, 'alice', $first, 1000) === null, 'grant at expiry');
for ($i = 0; $i < pdf_delivery_access::LIMIT; $i++) pdf_delivery_access::issue($session, 'alice', 'b', 101);
check(count($session['pdf_delivery_grants']) === pdf_delivery_access::LIMIT, 'bounded session grants');
check(pdf_delivery_access::resolve($session, 'alice', $first, 101) === null, 'oldest grant evicted');
echo "PDF delivery contract: $checks checks passed\n";
