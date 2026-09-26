<?php
// php tests/pdfmaker_output_test.php <runtime-fbp-dir> <isolated-output-dir>
$runtime = realpath($argv[1] ?? '');
$out = realpath($argv[2] ?? '');
if (!$runtime || !$out) throw new InvalidArgumentException('Specify runtime FBP and existing output directories');
chdir($runtime);
// Match the existing PDF renderer's legacy-compatible error level.
error_reporting(E_ERROR | E_PARSE);
require_once 'interface/Controller.php';
require_once 'lib/Controller_class.php';
require_once 'lib/Dirs.php';
require_once 'lib/pdfmaker/pdfmaker_class.php';

class OutputController extends Controller_class {
    public string $failure = '';
    function __construct(string $out) { $this->dirs = new Dirs(); $this->dirs->datadir = $out; }
    function save_file($filename, $data) {
        parent::save_file($filename, $this->failure === 'short' ? substr($data, 0, 10) : $data);
        if ($this->failure === 'save') throw new RuntimeException('Simulated partial save failure');
    }
    function res_saved_file($filename, $download_name = null) {
        if ($this->failure === 'send') throw new RuntimeException('Simulated response failure');
        parent::res_saved_file($filename, $download_name);
    }
}
function document(Controller $ctl, string $label): pdfmaker_class {
    $pdf = $ctl->create_pdfmaker();
    $pdf->setPageLayout(['pagesize'=>'A4', 'font'=>'gothic']);
    $pdf->addText('請求書 ' . $label, ['fontsize'=>18]);
    $pdf->addTable([['項目','金額'],['参加費','4,000']], ['columnsize'=>[70,30], 'columnalign'=>['L','R']]);
    $pdf->addPage();
    $pdf->addText('明細 ' . $label);
    return $pdf;
}
function check($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function parsed(string $file): string {
    $proc = proc_open(['pdftotext', $file, '-'], [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
    $text = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($proc) === 0, 'PDF parse failed: ' . $err);
    return $text;
}
$ctl = new OutputController($out);
if (($argv[3] ?? '') === 'download') {
    document($ctl, $argv[4])->download_pdf("請求書\r\n.pdf");
    throw new RuntimeException('download_pdf did not terminate the response');
}

$pdf = document($ctl, 'Sample A');
$headers = headers_list(); $level = ob_get_level();
ob_start(); $bytes = $pdf->get_pdf_data(); $unexpected = ob_get_clean();
check($unexpected === '', 'get_pdf_data emitted response bytes');
check(headers_list() === $headers && ob_get_level() === $level, 'get_pdf_data changed headers/buffers');
check(strncmp($bytes, '%PDF-', 5) === 0, 'Missing PDF signature');
file_put_contents($out . '/data.pdf', $bytes);
$text = parsed($out . '/data.pdf');
foreach (['請求書', 'Sample A', '4,000', '明細'] as $expected) check(str_contains($text, $expected), 'Missing PDF content');
check(substr_count($text, "\f") === 2, 'Expected two pages');

ob_start(); $pdf->create_pdf(); $inline = ob_get_clean();
file_put_contents($out . '/inline.pdf', $inline);
check(strncmp($inline, '%PDF-', 5) === 0 && parsed($out . '/inline.pdf') === $text, 'Legacy inline output changed');
file_put_contents($out . '/repeat.pdf', $pdf->get_pdf_data());
check(parsed($out . '/repeat.pdf') === $text, 'Repeated generation changed document state');

// Direct S mode was broken in the stream-backed tFPDF fork; test it independently.
$raw = new CustomizedPDF(); $raw->AddPage(); $raw->SetFont('Arial', '', 12); $raw->Cell(180, 10, 'Raw stream');
$rawBytes = $raw->Output('raw.pdf', 'S');
check(strncmp($rawBytes, '%PDF-', 5) === 0, 'tFPDF S returned its obsolete empty buffer');
file_put_contents($out . '/raw.pdf', $rawBytes);
check(str_contains(parsed($out . '/raw.pdf'), 'Raw stream'), 'Raw stream PDF is incomplete');

foreach (['save','send','short'] as $failure) {
    $ctl->failure = $failure;
    try { $pdf->download_pdf('請求書.pdf'); throw new LogicException('Expected injected failure'); }
    catch (RuntimeException $e) {
        $expected = ['save'=>'Simulated partial save failure', 'send'=>'Simulated response failure', 'short'=>'Could not save the complete PDF for download'];
        check($e->getMessage() === $expected[$failure], 'Unexpected failure');
        check(glob($out . '/upload/pdf_download_*.pdf') === [], 'Temporary file survived ' . $failure . ' failure');
    }
}
$ctl->failure = '';
try { (new pdfmaker_class())->download_pdf(); throw new RuntimeException('Missing Controller was accepted'); }
catch (LogicException $e) {}

// Real res_saved_file exits; run concurrent children to verify shutdown cleanup.
$children = [];
foreach (['Concurrent A', 'Concurrent B'] as $label) {
    $proc = proc_open([PHP_BINARY, __FILE__, $runtime, $out, 'download', $label], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
    $children[] = [$proc, $pipes, $label];
}
foreach ($children as [$proc, $pipes, $label]) {
    $bytes = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($proc) === 0, 'Download child failed: ' . $err);
    check(strncmp($bytes, '%PDF-', 5) === 0, 'Download did not return PDF bytes');
    $file = $out . '/' . str_replace(' ', '-', $label) . '.pdf'; file_put_contents($file, $bytes);
    $text = parsed($file);
    check(str_contains($text, $label), 'Concurrent downloads crossed documents');
}
check(glob($out . '/upload/pdf_download_*.pdf') === [], 'Temporary file survived exit');
echo "PASS: PDF data, no response side effects, parsing/two pages, repeated generation, legacy inline, raw S mode, partial-save/send failure cleanup, concurrent downloads and exit cleanup\n";
