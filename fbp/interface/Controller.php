<?php

include_once "ctl/ctl_chat.php";
include_once "ctl/ctl_db.php";
include_once "ctl/ctl_files.php";
include_once "ctl/ctl_fw.php";
include_once "ctl/ctl_media.php";
include_once "ctl/ctl_security.php";
include_once "ctl/ctl_square.php";
include_once "ctl/ctl_ui.php";

interface Controller extends ctl_chat, ctl_db, ctl_files, ctl_fw, ctl_media, ctl_security, ctl_square, ctl_ui {
	public function set_prohibit_new_db(bool $flag): void;
	public function get_prohibit_new_db(): bool;
	public function get_dsp_channel(): string;
	public function set_dsp_channel(string $channel): void;
	public function set_dsp_mcp_subject(?array $subject): void;
	public function freeze_dsp_channel(): void;
	public function get_dsp_mcp_subject(): ?array;
	public function get_dsp_system_subject(): ?array;
	public function set_dsp_system_subject(?array $subject): void;
	
}
