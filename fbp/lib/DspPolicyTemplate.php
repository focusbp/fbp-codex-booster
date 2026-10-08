<?php
/** Deterministic source template, written to projects only by the authorized creation task. */
final class DspPolicyTemplate {
    public static function generate(string $className, array $rules): array {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D',$className)) throw new InvalidArgumentException('Invalid DSP class');
        $methods = [
            'add'=>['authorizeInsert(array $newRow): void'],
            'read'=>['authorizeRead(array $request): void','inspectRead(array $row): DspReadDecision'],
            'update'=>['authorizeUpdate(array $before, array $after, array $submittedFields): void'],
            'delete'=>['authorizeDelete(array $before): void'],
        ];
        $source = "<?php\nfinal class $className implements DspInterface {\n".
            '    public function __construct(private Controller $ctl, private readonly string $channel) {}'."\n";
        foreach ($methods as $op=>$signatures) {
            $rule = $rules[$op] ?? ['mode'=>'allow','conditions'=>''];
            if (!in_array($rule['mode'],['allow','deny','custom'],true)) throw new InvalidArgumentException('Invalid DSP mode');
            if ($rule['mode']==='custom' && trim((string)($rule['conditions'] ?? ''))==='') throw new InvalidArgumentException('Custom condition required');
            foreach ($signatures as $signature) {
                $read = str_starts_with($signature,'inspectRead');
                $body = match($rule['mode']) {
                    'allow'=>$read ? 'return new DspReadDecision(true, null);' : 'return;',
                    'deny'=>"throw new DspException('panel_policy', 'operation_denied');",
                    'custom'=>"throw new DspException('custom_pending', 'implementation_required');",
                };
                $source .= "    public function $signature { $body }\n";
            }
        }
        return ['php'=>$source."}\n"];
    }
}
