<?php

class RecordIdentifier {
    public static function valid($prefix): bool {
        return is_string($prefix) && ($prefix === '' || preg_match('/\A\p{L}[\p{L}\p{M}0-9_-]{0,63}\z/uD', $prefix) === 1);
    }

    public static function error(): string {
        return 'IDの接頭辞は日本語などの文字で始まる64文字以内の文字・半角数字・ハイフン・アンダースコアで入力してください。';
    }
}
