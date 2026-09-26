<?php

class RecordIdentifier {
    public static function valid($prefix): bool {
        return is_string($prefix) && ($prefix === '' || preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/D', $prefix) === 1);
    }

    public static function error(): string {
        return 'IDの接頭辞は半角英字で始まる64文字以内の英数字・ハイフン・アンダースコアで入力してください。';
    }
}
