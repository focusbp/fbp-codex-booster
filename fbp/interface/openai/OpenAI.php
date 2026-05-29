<?php

namespace openai;

interface OpenAI {

	/** セッション（会話履歴）をクリア */
	public function clear_messages(): void;

	/** 現在の会話履歴を取得 */
	public function get_messages(): array;

	/** system メッセージを履歴に追加 */
	public function add_system(string $content): void;

	/** user メッセージを履歴に追加 */
	public function add_user(string $content): void;
	
	public function add_assistant(string $content): void;

	/**
	 * メッセージを送り、Function Calling を自動処理して最終レスポンスを返す
	 * @param mixed $input string|array|null
	 * @param array $options 例: ['auto_tools' => true]
	 */
	public function respond($input = null): ?Response;
	
	public function respondStream($messages, array $options = []): void;
	
	public function delete_all_files(?string $purpose = null): array;
	
	public function set_messages_history_max($max);
	
}
