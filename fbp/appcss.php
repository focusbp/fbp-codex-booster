<?php


ini_set('display_errors',1);
error_reporting(E_ALL & ~E_NOTICE);

header('Content-Type: text/css');
header("Cache-Control:no-cache,no-store,must-revalidate,max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma:no-cache");

$class = $_GET["class"] ?? "";
$css_class = $_GET["css_class"] ?? "";
if(empty($css_class)){
	$css_class = $class;
}

include("lib/Dirs.php");
$dir_class = new Dirs();

if($class == "base"){
	$dir_base = $dir_class->get_class_dir("base");
	$file = $dir_base . "/style.css";
	$css = file_get_contents($file);
	echo $css;
	
	// FW
	$appdir = $dir_class->appdir_fw;
	$dirs = scandir($appdir);
		foreach($dirs as $dir){
			if($dir != "." && $dir != ".."){
				if($dir === "public_pages"){
					continue;
				}
				if(is_dir("$appdir/$dir")){
					if(is_file("$appdir/$dir/style.css")){
						$css_project = getcss("$appdir/$dir/style.css",$dir);
					echo "\n";
					echo $css_project;
				}
			}
		}
	}
	
	// User
	$appdir = $dir_class->appdir_user;
	$dirs = scandir($appdir);
		foreach($dirs as $dir){
			if($dir != "." && $dir != ".."){
				if($dir === "public_pages"){
					continue;
				}
				if(is_dir("$appdir/$dir")){
					if(is_file("$appdir/$dir/style.css")){
						$css_project = getcss("$appdir/$dir/style.css",$dir);
					echo "\n";
					echo $css_project;
				}
			}
		}
	}

}else{
	$file = $dir_class->get_class_dir($css_class) . "/style.css";
	echo getcss($file,$class);
}


function getcss($file,$class){
	if(is_file($file)){
		$css = file_get_contents($file);
		$add_class = ".class_style_" . $class . " ";
		$newcss = "";
		$buffer = "";
		$stack = [];
		$length = strlen($css);

		for($i = 0; $i < $length; $i++){
			$char = $css[$i];

			if($char === "{"){
				$prelude = $buffer;
				$trimmed = trim($prelude);
				$parent = empty($stack) ? null : end($stack);

				if($trimmed !== "" && ($parent === null || $parent === "at" || $parent === "keyframes")){
					if(strpos($trimmed, "@") === 0){
						$newcss .= $prelude . "{";
						if(preg_match('/^@(-[a-z0-9]+-)?keyframes\b/i', $trimmed)){
							$stack[] = "keyframes";
						}else{
							$stack[] = "at";
						}
					}else{
						if($parent !== "keyframes" && should_prefix_selector($trimmed)){
							$newcss .= prefix_selector_prelude($prelude, $add_class) . "{";
						}else{
							$newcss .= $prelude . "{";
						}
						$stack[] = "rule";
					}
					$buffer = "";
					continue;
				}

				$buffer .= $char;
				continue;
			}

			if($char === "}"){
				$newcss .= $buffer . "}\n";
				$buffer = "";
				if(!empty($stack)){
					array_pop($stack);
				}
				continue;
			}

			$buffer .= $char;
		}

		if($buffer !== ""){
			$newcss .= $buffer;
		}
		return $newcss;
	}
}

function should_prefix_selector($selector){
	if(strpos($selector, "%") !== false){
		return false;
	}
	if(preg_match('/(^|,)\s*body\b/i', $selector)){
		return false;
	}
	if(strpos($selector, "*") !== false){
		return false;
	}
	if(strpos($selector, ".multi_dialog") !== false){
		return false;
	}
	return true;
}

function prefix_selector_prelude($prelude, $add_class){
	$selectors = split_selector_list($prelude);
	$prefixed = [];
	foreach($selectors as $selector){
		$trimmed = trim($selector);
		if($trimmed === ""){
			continue;
		}
		if(strpos($trimmed, trim($add_class)) === 0){
			$prefixed[] = $trimmed;
			continue;
		}
		$prefixed[] = $add_class . $trimmed;
	}
	if(empty($prefixed)){
		return $prelude;
	}
	return implode(", ", $prefixed);
}

function split_selector_list($selector_text){
	$result = [];
	$current = "";
	$paren_depth = 0;
	$bracket_depth = 0;
	$length = strlen($selector_text);

	for($i = 0; $i < $length; $i++){
		$char = $selector_text[$i];
		if($char === "("){
			$paren_depth++;
		}elseif($char === ")" && $paren_depth > 0){
			$paren_depth--;
		}elseif($char === "["){
			$bracket_depth++;
		}elseif($char === "]" && $bracket_depth > 0){
			$bracket_depth--;
		}

		if($char === "," && $paren_depth === 0 && $bracket_depth === 0){
			$result[] = $current;
			$current = "";
			}else{
			$current .= $char;
			}
		}
	$result[] = $current;
	return $result;
	}
