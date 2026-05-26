<?php

//---------------------------------------
// イメージのグレースケールフィルター（JPG画像に変換する）
//---------------------------------------
function image_grayscale($file,$newfile){

	$type = exif_imagetype($file);
	if($type == IMAGETYPE_JPEG
		|| $type == IMAGETYPE_GIF
		|| $type == IMAGETYPE_PNG
	){
		
		switch ($type) {
			case 1 : 
           		$image = imageCreateFromGif($file); 
			break; 
			case 2 : 
				$image = imageCreateFromJpeg($file); 
			break; 
			case 3 : 
				$image = imageCreateFromPng($file); 
			break; 
		}
		
		if($image && imagefilter($image, IMG_FILTER_GRAYSCALE)){
			
			imagejpeg($image,
				$newfile,    // 出力するファイル名（省略すると画面に表示する）
				100                // 画像精度（この例だと100%で作成）
			);
			
		}else{
			if (PHP_VERSION_ID < 80000) {
				imagedestroy($image);
			}
			throw new Exception("GrayScale Filter Error");
		}
		if (PHP_VERSION_ID < 80000) {
			imagedestroy($image);
		}
	}
}


//----------------------------------------
// イメージのリサイズ
//----------------------------------------
function imageresize($file,$width){
	
	$type = exif_imagetype($file);
	
	if($type == IMAGETYPE_JPEG
		|| $type == IMAGETYPE_GIF
		|| $type == IMAGETYPE_PNG
	){

		// 元画像のファイルサイズを取得
		list($image_w, $image_h) = getimagesize($file);
		 
		//元画像の比率を計算し、高さを設定
		$proportion = $image_w / $image_h;
		$height = $width / $proportion;
		
		//高さが幅より大きい場合は、高さを幅に合わせ、横幅を縮小
		if($proportion < 1){
			$height = $width;
			$width = $width * $proportion;
		}
		 
		// サイズを指定して、背景用画像を生成
		$canvas = imagecreatetruecolor($width, $height);
		 
		// ファイル名から、画像インスタンスを生成
		switch ($type) {
			case 1 : 
           		$image = imageCreateFromGif($file); 
			break; 
			case 2 : 
				$image = imageCreateFromJpeg($file); 
			break; 
			case 3 : 
				$image = imageCreateFromPng($file); 
			break; 
		}
		 
		// 背景画像に、画像をコピーする
		imagecopyresampled($canvas,  // 背景画像
			 $image,   // コピー元画像
			 0,        // 背景画像の x 座標
			 0,       // 背景画像の y 座標
			 0,        // コピー元の x 座標
			 0,        // コピー元の y 座標
			 $width,   // 背景画像の幅
			 $height,  // 背景画像の高さ
			 $image_w, // コピー元画像ファイルの幅
			 $image_h  // コピー元画像ファイルの高さ
		);
		 
		// 画像を出力する
		imagejpeg($canvas,           // 背景画像
			$file,    // 出力するファイル名（省略すると画面に表示する）
			100                // 画像精度（この例だと100%で作成）
		);
		 
		// メモリを開放する
		if (PHP_VERSION_ID < 80000) {
			imagedestroy($canvas);
		}
	}
}
