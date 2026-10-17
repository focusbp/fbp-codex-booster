<?php
/** Deliberately small arithmetic grammar. Never evaluates PHP or user functions. */
final class ExcelFormula {
    private array $tokens = [];
    private int $pos = 0;
    private int $depth = 0;
    private array $refs = [];

    public function parse(string $expression): array {
        $expression = trim($expression);
        if (isset($expression[0]) && $expression[0] === '=') $expression = substr($expression, 1);
        if ($expression === '' || strlen($expression) > 1024) throw new InvalidArgumentException('計算式は1〜1024文字で入力してください。');
        $this->tokens = []; $this->pos = 0; $this->depth = 0; $this->refs = [];
        $offset = 0;
        while ($offset < strlen($expression)) {
            if (!preg_match('/\G\s*(?:(\d+(?:\.\d*)?|\.\d+)|([A-Za-z]+)|([+*\/()\-]))/A', $expression, $m, 0, $offset)) {
                if (trim(substr($expression, $offset)) === '') break;
                throw new InvalidArgumentException('計算式に使用できない文字があります。');
            }
            $offset += strlen($m[0]);
            $this->tokens[] = !empty($m[1]) || ($m[1] ?? '') === '0' ? ['n', $m[1]] : (!empty($m[2]) ? ['r', strtolower($m[2])] : [$m[3], $m[3]]);
            if (count($this->tokens) > 256) throw new InvalidArgumentException('計算式が長すぎます。');
        }
        $tree = $this->expression();
        if ($this->pos !== count($this->tokens)) throw new InvalidArgumentException('計算式の演算子または括弧を確認してください。');
        return ['tree' => $tree, 'refs' => array_keys($this->refs)];
    }
    private function expression(): array {
        $a = $this->term();
        while (in_array($this->peek(), ['+', '-'], true)) { $op = $this->tokens[$this->pos++][0]; $a = [$op, $a, $this->term()]; }
        return $a;
    }
    private function term(): array {
        $a = $this->factor();
        while (in_array($this->peek(), ['*', '/'], true)) { $op = $this->tokens[$this->pos++][0]; $a = [$op, $a, $this->factor()]; }
        return $a;
    }
    private function peek(): string { return $this->tokens[$this->pos][0] ?? ''; }
    private function factor(): array {
        if (++$this->depth > 32) throw new InvalidArgumentException('括弧や符号の入れ子が深すぎます。');
        $t = $this->tokens[$this->pos++] ?? null;
        if (!$t) throw new InvalidArgumentException('計算式の値が不足しています。');
        if ($t[0] === 'n') $result = $t;
        elseif ($t[0] === 'r') {
            if (!preg_match('/^(?:[a-z]|a[a-z])$/', $t[1])) throw new InvalidArgumentException('参照列はA〜AZです。');
            $this->refs[$t[1]] = true; $result = $t;
        } elseif ($t[0] === '+' || $t[0] === '-') $result = ['u' . $t[0], $this->factor()];
        elseif ($t[0] === '(') {
            $result = $this->expression();
            if ($this->peek() !== ')') throw new InvalidArgumentException('閉じ括弧が不足しています。');
            $this->pos++;
        } else throw new InvalidArgumentException('計算式の値または括弧を確認してください。');
        --$this->depth; return $result;
    }
    public static function evaluate(array $t, array $row): ?float {
        if ($t[0] === 'n') $v = (float)$t[1];
        elseif ($t[0] === 'r') {
            $v = $row[$t[1]] ?? '';
            if ($v === '' || $v === null) return null;
            if (!is_numeric($v)) throw new RuntimeException('数値以外が含まれています。');
            $v = (float)$v;
        } elseif ($t[0] === 'u+' || $t[0] === 'u-') {
            $v = self::evaluate($t[1], $row);
            if ($v === null) return null;
            if ($t[0] === 'u-') $v = -$v;
        } else {
            $a = self::evaluate($t[1], $row); $b = self::evaluate($t[2], $row);
            if ($a === null || $b === null) return null;
            if ($t[0] === '/' && $b == 0) throw new RuntimeException('0では割れません。');
            switch ($t[0]) { case '+': $v = $a + $b; break; case '-': $v = $a - $b; break; case '*': $v = $a * $b; break; default: $v = $a / $b; }
        }
        if (!is_finite($v)) throw new RuntimeException('計算結果が数値の範囲を超えています。');
        return $v;
    }
    public static function plan(array $columns): array {
        $trees = []; $order = []; $marks = [];
        foreach ($columns as $name => $c) {
            if (($c['mode'] ?? 'input') !== 'calculation' || empty($c['generated'])) continue;
            if (!in_array($c['type'], ['number', 'float'], true)) throw new InvalidArgumentException('「計算」は数値型で設定してください。');
            $trees[$name] = (new self)->parse($c['formula'] ?? '');
            foreach ($trees[$name]['refs'] as $ref) {
                $source = $columns[$ref] ?? [];
                if (empty($source['generated']) || !in_array($source['type'] ?? '', ['number','float'], true)) throw new InvalidArgumentException(strtoupper($ref) . 'は使用済みの数値列にしてください。');
                if (($source['mode'] ?? 'input') === 'function') throw new InvalidArgumentException('簡易式から関数列は参照できません。');
            }
        }
        $visit = function ($name) use (&$visit, &$marks, &$order, $trees) {
            if (($marks[$name] ?? 0) === 1) throw new InvalidArgumentException('計算式が循環参照しています。');
            if (($marks[$name] ?? 0) === 2) return;
            $marks[$name] = 1;
            foreach ($trees[$name]['refs'] as $ref) if (isset($trees[$ref])) $visit($ref);
            $marks[$name] = 2; $order[] = $name;
        };
        foreach ($trees as $name => $_) $visit($name);
        return ['order' => $order, 'trees' => $trees];
    }
    public static function calculate(array $columns, array $row): array {
        $plan = self::plan($columns); $errors = [];
        foreach ($plan['order'] as $name) {
            try {
                foreach ($plan['trees'][$name]['refs'] as $ref) if (isset($errors[$ref])) throw new RuntimeException('参照先の' . strtoupper($ref) . 'がERRORです。');
                $v = self::evaluate($plan['trees'][$name]['tree'], $row);
                $digits = (int)($columns[$name]['digits'] ?? 0);
                if ($v !== null) {
                    $factor = pow(10, $digits);
                    switch ($columns[$name]['rounding'] ?? 'round') {
                        case 'truncate': $v = ($v < 0 ? ceil($v * $factor) : floor($v * $factor)) / $factor; break;
                        case 'away': $v = ($v < 0 ? floor($v * $factor) : ceil($v * $factor)) / $factor; break;
                        default: $v = round($v, $digits, PHP_ROUND_HALF_UP);
                    }
                    if (!is_finite($v)) throw new RuntimeException('計算結果が数値の範囲を超えています。');
                }
                if ($v !== null && isset($columns[$name]['length']) && strlen((string)$v)>(int)$columns[$name]['length']) throw new RuntimeException('計算結果が保存可能な桁数を超えています。');
                $row[$name] = $v === null ? '' : $v;
            } catch (Throwable $e) { $errors[$name] = $e->getMessage(); $row[$name] = ''; }
        }
        return ['row' => $row, 'errors' => $errors];
    }
}
