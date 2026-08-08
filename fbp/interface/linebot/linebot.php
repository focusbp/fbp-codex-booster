<?php

interface linebot
{
    public function getLastError(): ?string;

    public function handle_webhook(): ?array;
    
    public function nextEvent(): ?array;
    
    public function getUserID(): ?string;

    /** テキスト返信（1件） */
    public function set_text(string $text);
    
    public function set_sticker(string $packageId, string $stickerId);

    /** Flex Messageを返信キューに追加 */
    public function set_flex(string $altText, array $contents): bool;
    
    public function send_reply(): bool;

    /** 1:1のプロフィール取得 */
    public function getUserProfile(string $userId): ?array;

    /** グループ内メンバー取得 */
    public function getGroupMemberProfile(string $groupId, string $userId): ?array;

    /** ルーム内メンバー取得 */
    public function getRoomMemberProfile(string $roomId, string $userId): ?array;
    
    public function log($text): void;
    
    public function send_push(string $userId): bool;
    
    public function set_location(string $place_name, string $address, float $latitude, float $longitude): void;
    
    public function set_location_from_string(string $place_name, string $address, string $latlng): void;
    
    public function clear_queue(): void;
}
