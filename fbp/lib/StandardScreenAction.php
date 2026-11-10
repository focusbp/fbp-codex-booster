<?php

/** One synchronous standard-screen mutation. Instances are created by db_exe only. */
final class StandardScreenAction {
    private bool $completed = false;

    public function __construct(
        public readonly string $operation,
        public readonly string $table,
        public readonly array $setting,
        public readonly ?array $child,
        public readonly bool $single,
        private Controller $ctl,
        private FFM $database,
        private Closure $standardCallback,
        private Closure $finishCallback,
        private Closure $deleteCallback,
    ) {}

    public static function validClass(mixed $name): bool {
        return is_string($name) && ($name === '' ||
            (strlen($name) <= 255 && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)));
    }

    /** Returns false only for an unset class or an unimplemented operation. */
    public function dispatch(string $name): bool {
        if ($name === '') return false;
        if (!self::validClass($name)) throw new RuntimeException('標準画面カスタマイズクラス名が不正です。');
        $file = $this->ctl->dirs->appdir_user . '/' . $name . '/' . $name . '.php';
        if (!is_file($file)) throw new RuntimeException('標準画面カスタマイズクラスが見つかりません: ' . $name);
        require_once $file;
        if (!class_exists($name, false)) throw new RuntimeException('標準画面カスタマイズクラスが定義されていません: ' . $name);
        $reflection = new ReflectionClass($name);
        // Do not run a constructor for an operation which uses the default implementation.
        if (!$reflection->hasMethod($this->operation)) return false;
        $method = $reflection->getMethod($this->operation);
        if (!$method->isPublic() || $method->isStatic() || $reflection->isAbstract()) {
            throw new RuntimeException('標準画面カスタマイズメソッドはpublicインスタンスメソッドにしてください。');
        }
        $ctor = $reflection->getConstructor();
        $instance = $ctor && $ctor->getNumberOfParameters() > 0
            ? $reflection->newInstance($this->ctl) : $reflection->newInstance();
        $method->invoke($instance, $this->ctl, $this);
        // Returning without standard()/finish() intentionally cancels the mutation.
        return true;
    }

    public function db(): FFM { return $this->database; }

    /** Read AFTER opening all other databases required for business validation. */
    public function current(): ?array {
        if ($this->single) {
            $rows = $this->database->getall();
            if (count($rows) > 1) throw new RuntimeException('単一レコードの件数が不正です。');
            return $rows ? reset($rows) : null;
        }
        $id = $this->ctl->decrypt_post('id');
        return $id ? $this->database->get($id) : null;
    }

    /** Retains the framework's validation, file handling, UI and post_action_class. */
    public function standard(?array $post = null): void {
        if ($this->ctl->count_res_error_message() > 0) return;
        $this->once();
        ($this->standardCallback)($post);
    }

    /** Only call after the customized persistence/file handling has succeeded. */
    public function finish(array $after, ?array $before = null): void {
        if ($this->ctl->count_res_error_message() > 0) return;
        $this->once();
        ($this->finishCallback)($after, $before);
    }

    /** Default recursive deletion, including configured child cascades and files. */
    public function deleteRecord(int $id): void {
        if ($this->operation !== 'delete' || $this->completed || $this->ctl->count_res_error_message() > 0) {
            throw new LogicException('削除処理を実行できません。');
        }
        ($this->deleteCallback)($id);
    }

    private function once(): void {
        if ($this->completed) throw new LogicException('標準処理・完了処理を二重に実行できません。');
        $this->completed = true;
    }
}
