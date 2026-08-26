<?php

class email_format {

	private $fmt_email_format;

	function __construct(Controller $ctl) {
		$this->fmt_email_format = $ctl->db("email_format");
	}

	//index page
	function page(Controller $ctl) {

		$post = $ctl->POST();
		$ctl->assign('post', $post);
		$button = (string) ($post['button'] ?? '');
		$search_template_name = (string) ($ctl->get_session("search_template_name_em_tmp") ?? '');
		$posted_search_template_name = trim((string) ($post['search_template_name'] ?? ''));
		$window = (string) ($post["window"] ?? '');

		if ($button === "reset") {
			$search_template_name = "";
			$ctl->set_session("search_template_name_em_tmp", $search_template_name);
		}
		if ($posted_search_template_name !== '') {
			$search_template_name = $posted_search_template_name;
			$ctl->set_session("search_template_name_em_tmp", $search_template_name);
		}

		$items = $this->fmt_email_format->filter(["template_name"], [$search_template_name], false, 'AND', 'sort', SORT_ASC);
		$ctl->assign("items", $items);

		if($window === "window"){
			$ctl->show_multi_dialog("email_format", "index.tpl", $ctl->t("email_format.dialog.index"),1000);
		}else{
			$ctl->reload_area("#tabs-mail","index.tpl");
		}
	}
	

	//view add page
	function add(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);
		$ctl->show_multi_dialog("add_email_format", "add.tpl", $ctl->t("email_format.dialog.add"), 1000, true, true);
	}

	//save add data
	function add_exe(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);
		//validation
		$errors = $this->validate_email_format_data($ctl, $post, "add");
		if (count($errors)) {
			$ctl->assign('errors', $errors);
			$this->add($ctl);
			return;
		}

		$post['created_at'] = time();
		$id = $this->fmt_email_format->insert($post);

		//close adding page
		$ctl->close_multi_dialog("add_email_format");
		$this->page($ctl);
	}

	//validation
	function validate_email_format_data(Controller $ctl, $post, $page) {
		$errors = [];

		return $errors;
	}

	//view edit page
	function edit(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign("post", $post);
		$id = (int) ($post['id'] ?? 0);
		$data = $this->fmt_email_format->get($id);
		$data = array_merge($data, $post);
		$ctl->assign("data", $data);
		
		// Making screen dropdown
		$fmt_screen = $ctl->db("screen","db");
		$slist = $fmt_screen->getall("tb_name", SORT_ASC);
		$screen_opt = [];
		foreach ($slist as $s) {
			if (!empty($s["tb_name"]) && !empty($s["screen_name"])) {
				$screen_opt[$s["id"]] = $s["tb_name"];  // show only tb_name
			}
		}
		$ctl->assign("screen_opt", $screen_opt);
		

		$ctl->ajax("email_format","database_field_reference");
		$ctl->show_multi_dialog("edit_email_format_" . $id, "edit.tpl", $ctl->t("email_format.dialog.edit"), 1000, true, true);
	}
	
	function database_field_reference(Controller $ctl){
		$post = $ctl->POST();
		$db_id = (int) ($post["db_id"] ?? 0);
		
		// db
		$ffm_db = $ctl->db("db","db");
		$ffm_field = $ctl->db("db_fields","db");
		
		// Dropdown
		$db_opt = [];
		$db_opt[""] = "";
		$db_list = $ffm_db->getall("tb_name",SORT_ASC);
		foreach($db_list as $d){
			$db_opt[$d["id"]] = $d["tb_name"];
		}
		$ctl->assign("db_opt",$db_opt);
		$ctl->assign("db_id",$db_id);
		
		// Fields
		if($db_id > 0){
			$db = $ffm_db->get($db_id);
			$ctl->assign("db",$db);
			$field_list = $ffm_field->select("db_id",$db_id,true,"AND","parameter_name",SORT_ASC);
			
			foreach($field_list as $key=>&$f){
				if(strpos("number/float/textarea/date/time/datetime/year_month",$f["type"]) !== false){
					$f["type_condition"] = 1;
				}else if(($f["type"]=="dropdown" || $f["type"]=="radio") && endsWith($f["constant_array_name"], "_opt")){
					$f["type_condition"] = 2;
				}else{
					// あとでcheckboxの機能を付けるがいまは削除
					unset($field_list[$key]);
				}
			}
			
			$ctl->assign("field_list",$field_list);
		}
		
		$ctl->reload_area(".emailformat_edit_right", "field_reference.tpl");
		
	}

	//save edited data
	function edit_exe(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);
		//validation
		$errors = $this->validate_email_format_data($ctl, $post, "edit");
		if (count($errors)) {
			$ctl->assign('errors', $errors);
			$this->edit($ctl);
			return;
		}

		$id = (int) ($post['id'] ?? 0);
		$data = $this->fmt_email_format->get($id);
		foreach ($_POST as $key => $value) {
			$data[$key] = $value;
		}

		$data['updated_at'] = time();
		$this->fmt_email_format->update($data);

		$ctl->close_multi_dialog("edit_email_format_" . $id);
		$this->page($ctl);
	}

	//view delete page
	function delete(Controller $ctl) {
		$id = $ctl->POST("id");
		$data = $this->fmt_email_format->get($id);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("delete", "delete.tpl", $ctl->t("email_format.dialog.delete"), 600, true, true);
	}

	//delete data form database
	function delete_exe(Controller $ctl) {
		$id = $ctl->POST("id");
		//file delete
		$data = $this->fmt_email_format->get($id);

		//deleting child data
		$this->fmt_email_format->delete($id);
		$ctl->close_multi_dialog("delete");
		$this->page($ctl);
	}

	function sort(Controller $ctl) {
		$post = $ctl->POST();
		$logArr = explode(',', (string) ($post['log'] ?? ''));
		$c = 0;
		foreach ($logArr as $id) {
			$d = $this->fmt_email_format->get($id);
			$d['sort'] = $c;
			$this->fmt_email_format->update($d);
			$c++;
		}
	}

	function json_upload(Controller $ctl) {
		$ctl->show_multi_dialog("upload", "upload.tpl", $ctl->t("email_format.dialog.upload"), 600, true, true);
	}

	function json_upload_exe(Controller $ctl) {

		$save_filename = 'email_template.json';
		$ctl->save_posted_file('email_templates_file', $save_filename);

		//get saved file path
		$file_path = $ctl->get_saved_filepath($save_filename);

		$json_string_templates = file_get_contents($file_path);
		$data_templates = json_decode($json_string_templates, true);

		foreach ($data_templates as $value) {
			$list = $this->fmt_email_format->select(['key'], [$value['key']]);
			if(count($list) ==0){
				$this->fmt_email_format->insert($value);
			}
		}
		$ctl->close_multi_dialog("upload");
		$this->page($ctl);
	}

	function json_download(Controller $ctl) {
		$post = $ctl->POST();
		$email_templates = $this->fmt_email_format->getall();
		$ctl->res_json($email_templates, (string) ($post['filename'] ?? 'email_template.json'));
	}
}
