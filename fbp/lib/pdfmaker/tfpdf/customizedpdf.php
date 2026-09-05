<?php


require('tfpdf.php');

class CustomizedPDF extends tFPDF {
	
	protected $totalPageNumber;
	protected $pagecountflg;
	protected $memoryImageTypes = [];
	
	protected $hidefooter = false;
	
	protected $pagenumber_firstpage_off = false;
	
	protected $pagenumber_font = "helvetica";
	
	protected $pagenumber_y_position = 0;

	protected $page_footer_callback = null;
	protected $page_footer_set = [];
	
	var $angle=0; //Rotate
	
	/*
	 * Table with multi-page columns
	 */

	var $tablewidths;
	var $footerset;
	var $tablealigns;
	var $filled_text;
	var $cell_rect;
	
	function hideFooter(){
		$this->hidefooter = true;
	}
	
	function pagenumber_firstpage_off(){
		$this->pagenumber_firstpage_off = true;
	}
	
	function resetPageTotal(){
		if(empty($this->totalPageNumber)){
			$this->totalPageNumber = array();
		}
		$this->totalPageNumber[$this->page] = "reset";
	}
	
	function setPageNumberFont($font){
		$this->pagenumber_font = $font;
	}
	
	function pagenumber_y_position($y){
		$this->pagenumber_y_position = $y;
	}

	function setPageFooterCallback($callback){
		if ($callback !== null && !is_callable($callback)) {
			$this->Error('Page footer callback must be callable or null.');
		}
		$this->page_footer_callback = $callback;
	}
	
	/*
	 * ページ番号の処理のためにコピーしてオーバーライド
	 */
	protected function _putpage($n)
	{
		$this->_newobj();
		$this->_put('<</Type /Page');
		$this->_put('/Parent 1 0 R');
		if(isset($this->PageInfo[$n]['size']))
			$this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->PageInfo[$n]['size'][0],$this->PageInfo[$n]['size'][1]));
		if(isset($this->PageInfo[$n]['rotation']))
			$this->_put('/Rotate '.$this->PageInfo[$n]['rotation']);
		$this->_put('/Resources 2 0 R');
		if(isset($this->PageLinks[$n]))
		{
			// Links
			$annots = '/Annots [';
			foreach($this->PageLinks[$n] as $pl)
			{
				$rect = sprintf('%.2F %.2F %.2F %.2F',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
				$annots .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';
				if(is_string($pl[4]))
					$annots .= '/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
				else
				{
					$l = $this->links[$pl[4]];
					if(isset($this->PageInfo[$l[0]]['size']))
						$h = $this->PageInfo[$l[0]]['size'][1];
					else
						$h = ($this->DefOrientation=='P') ? $this->DefPageSize[1]*$this->k : $this->DefPageSize[0]*$this->k;
					$annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>',$this->PageInfo[$l[0]]['n'],$h-$l[1]*$this->k);
				}
			}
			$this->_put($annots.']');
		}
		if($this->WithAlpha)
			$this->_put('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
		$this->_put('/Contents '.($this->n+1).' 0 R>>');
		$this->_put('endobj');
		// Page content
		if(!empty($this->AliasNbPages))
			$this->pages[$n] = str_replace($this->AliasNbPages,$this->page,$this->pages[$n]);
		

		
		if($this->pagecountflg){
			// 1回しか動かさない
			$resetpage = 1;
			$pcount=1;
			for($i=1;$i<=$this->page;$i++){
				if(($this->totalPageNumber[$i] ?? null) == "reset"){
					$resetpage = $i;
					$pcount=1;
					$this->totalPageNumber[$i] = $pcount;
				}else{
					for($c=$resetpage;$c<=$i;$c++){
						$this->totalPageNumber[$c] = $pcount;
					}
				}
				$pcount++;
			}
			$this->pagecountflg = false;
		}
		
		$this->pages[$n] = str_replace('tpn',$this->totalPageNumber[$n],$this->pages[$n]);
		
		$this->_putstreamobject($this->pages[$n]);
	}

	/*
	 * 左寄、右寄せ自動判定
	 */
	function autoAlign($str){
		$str = str_replace('円','',$str);
		$str = str_replace('\\','',$str);
		$str = str_replace('-','',$str);
		$str = str_replace(',','',$str);
		$str = str_replace('%','',$str);
		$str = str_replace('$','',$str);
		if(is_numeric($str)){
			return "R";
		}else{
			return "L";
		}
	}
	


	function _beginpage($orientation, $size) {
		
		$this->pagecountflg = true;
		
		$this->page++;
		if(!isset($this->pages[$this->page])) // solves the problem of overwriting a page if it already exists
			$this->pages[$this->page] = '';
		$this->state  =2;
		$this->x = $this->lMargin;
		$this->y = $this->tMargin;
		$this->FontFamily = '';
		// Check page size and orientation
		if($orientation=='')
			$orientation = $this->DefOrientation;
		else
			$orientation = strtoupper($orientation[0]);
		if($size=='')
			$size = $this->DefPageSize;
		else
			$size = $this->_getpagesize($size);
		if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1])
		{
			// New size or orientation
			if($orientation=='P')
			{
				$this->w = $size[0];
				$this->h = $size[1];
			}
			else
			{
				$this->w = $size[1];
				$this->h = $size[0];
			}
			$this->wPt = $this->w*$this->k;
			$this->hPt = $this->h*$this->k;
			$this->PageBreakTrigger = $this->h-$this->bMargin;
			$this->CurOrientation = $orientation;
			$this->CurPageSize = $size;
		}
		if($orientation!=$this->DefOrientation || $size[0]!=$this->DefPageSize[0] || $size[1]!=$this->DefPageSize[1])
			$this->PageSizes[$this->page] = array($this->wPt, $this->hPt);
	}

	function Footer() {
		if (!isset($this->page_footer_set[$this->page]) && is_callable($this->page_footer_callback)) {
			call_user_func($this->page_footer_callback, $this);
			$this->page_footer_set[$this->page] = true;
		}

		// Check if Footer for this page already exists (do the same for Header())
		if(!$this->hidefooter){
			if(!isset($this->footerset[$this->page])) {
				if($this->PageNo() == 1 && $this->pagenumber_firstpage_off){
					// Nothing
					
				}else{
					$this->SetY(-15 + $this->pagenumber_y_position);
					// Page number
					$tpn = 1;
					for($i=1;$i<=$this->PageNo();$i++){
						if(($this->totalPageNumber[$i] ?? null) == "reset"){
							$tpn = 1;
						}else{
							$tpn++;
						}
					}
					if($this->pagenumber_firstpage_off){
						$tpn = $tpn - 1;
					}
					$tmp_fontsize = $this->FontSizePt;
					$tmp_fontfamily = $this->FontFamily;
					$tmp_fontstyle = $this->FontStyle;
					$this->SetFont($this->pagenumber_font, "", 8);
					//$this->Cell(0,10,'Page '.$tpn.'',0,0,'C');
					$this->SetTextColor(0,0,0);
					$this->Cell(0,10,'' . $tpn,0,0,'C');
					//$this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
					// set footerset
						$this->footerset[$this->page] = true;
						$this->SetFont($tmp_fontfamily,$tmp_fontstyle,$tmp_fontsize);
					}
				}
			}
		}

	function registerMemoryImage($key, $data, $type = "png") {
		if (isset($this->images[$key])) {
			return;
		}

		$type = strtolower((string) $type);
		if ($type !== "png") {
			$this->Error('Unsupported memory image type: ' . $type);
		}

		$f = fopen('php://temp', 'rb+');
		if (!$f) {
			$this->Error('Unable to open temporary memory stream');
		}

		fwrite($f, $data);
		rewind($f);
		$info = $this->_parsepngstream($f, $key);
		fclose($f);

		$info['i'] = count($this->images) + 1;
		$this->images[$key] = $info;
		$this->memoryImageTypes[$key] = $type;
	}

	function ImageMemory($key, $x = null, $y = null, $w = 0, $h = 0, $link = '', $onlycheck = false) {
		if (!isset($this->images[$key])) {
			$this->Error('Unknown memory image key: ' . $key);
		}

		$info = $this->images[$key];

		if ($w == 0 && $h == 0) {
			$w = -96;
			$h = -96;
		}
		if ($w < 0) {
			$w = -$info['w'] * 72 / $w / $this->k;
		}
		if ($h < 0) {
			$h = -$info['h'] * 72 / $h / $this->k;
		}
		if ($w == 0) {
			$w = $h * $info['w'] / $info['h'];
		}
		if ($h == 0) {
			$h = $w * $info['h'] / $info['w'];
		}

		if (!$onlycheck) {
			if ($y === null) {
				if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
					$x2 = $this->x;
					$this->AddPage($this->CurOrientation, $this->CurPageSize);
					$this->x = $x2;
				}
				$y = $this->y;
				$this->y += $h;
			}

			if ($x === null) {
				$x = $this->x;
			}
			$w = (float) $w;
			$h = (float) $h;
			$x = (float) $x;
			$y = (float) $y;

			$this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w * $this->k, $h * $this->k, $x * $this->k, ($this->h - ($y + $h)) * $this->k, $info['i']));

			if ($link) {
				$this->Link($x, $y, $w, $h, $link);
			}
		}

		return ["h" => $h];
	}
	
	function pagebreak_for_morepagestable(){
		// Automatic page break
		$x = $this->x;
		$ws = $this->ws;
		if($ws>0)
		{
			$this->ws = 0;
			$this->_out('0 Tw');
		}
		$this->AddPage($this->CurOrientation,$this->CurPageSize);
		$this->x = $x;
		if($ws>0)
		{
			$this->ws = $ws;
			$this->_out(sprintf('%.3F Tw',$ws*$this->k));
		}
	}

	function morepagestable($datas, $lineheight=8,$marginleft=null,$fill=false,$set=array()) {
		
		// some things to set and 'remember'
		//$l = $this->lMargin;
		
		if(count($datas) == 0){
			return;
		}
		
		//いきなりブレイクする場合があるので、先にブレイクしておく
		if($this->y+$lineheight > $this->PageBreakTrigger &&$this->AcceptPageBreak())
		{
			$this->pagebreak_for_morepagestable();
		}
		
		if(!isset($marginleft)){
			$l = $this->lMargin;
		}else{
			$l = $this->lMargin + $marginleft;
		}
		$startheight = $h = $this->GetY();
		$startpage = $currpage = $maxpage = $this->page;


		// calculate the whole width
		$fullwidth = 0;
		foreach($this->tablewidths AS $width) {
			$fullwidth += $width;
		}
		
		$tmpheight = array();
		$this->page_max_height = array();
		
		if(isset($set["linewidth"])){
			$this->SetLineWidth($set["linewidth"]);
		}else{
			$this->SetLineWidth(0.2);
		}
		
		$tableline = [];
		$this->filled_text = [];
		$this->cell_rect = [];
		
		// Save
		$this->save("table");
		$saved_maxpage_table = $maxpage;
		
		// Now let's start to write the table
		foreach($datas AS $row => $data) {
			
			// Save
			$this->save("row");
			$saved_maxpage = $maxpage;
			
			$this->page = $currpage;
			// 横のラインを保存
			$tableline[$this->page][] = [$l,$h,$fullwidth+$l,$h];
			
			$cell_rect_row=[];

			// write the content and remember the height of the highest col
			foreach($data AS $col => $txt) {
				$col_align = $this->tablealigns[$col] ?? "L";
				$col_width = $this->tablewidths[$col] ?? ($this->tablewidths[count($this->tablewidths) - 1] ?? 0);
				$this->page = $currpage;
				$this->SetXY($l,$h);
				if($fill){
					$this->filled_text[] = [$col_width,$lineheight,$txt,0,$col_align,$l,$h,$this->page,$set["span_x"],$set["span_y"]];
				}
				$this->MultiCell($col_width,$lineheight,$txt,0,$col_align,false,$set["span_x"],$set["span_y"]);
				
				if($set["span_x"] > 0 || $set["span_y"] > 0){
					$cell_rect_row[] = [$l+$set["span_x"], $h+$set["span_y"], $col_width - $set["span_x"]*2,$this->y - $h - $set["span_y"] *2,$this->page];
				}
				
				$l += $col_width;
				
				if($this->page > $maxpage){
					$maxpage = $this->page;
				}
			}
			
			$this->cell_rect[] = $cell_rect_row;
			
			if(isset($set["rowbreak"])){
				if($saved_maxpage != $maxpage){
					// 改ページしているので戻す
					$this->undo("row");
					$this->pagebreak_for_morepagestable();
					$this->page_max_height[$maxpage] = $this->y;
					$cell_rect_row=[];

					// 消えた行を書く
					$this->page = $maxpage;
					$h = $this->page_max_height[$maxpage];
					if(!isset($marginleft)){
						$l = $this->lMargin;
					}else{
						$l = $this->lMargin + $marginleft;
					}
					foreach($data AS $col => $txt) {
						$col_align = $this->tablealigns[$col] ?? "L";
						$col_width = $this->tablewidths[$col] ?? ($this->tablewidths[count($this->tablewidths) - 1] ?? 0);
						$this->SetXY($l,$h);
						if($fill){
							$this->filled_text[] = [$col_width,$lineheight,$txt,0,$col_align,false,$set["span_x"],$set["span_y"],$this->page,$set["span_x"],$set["span_y"]];
						}
						$this->MultiCell($col_width,$lineheight,$txt,0,$col_align,false,$set["span_x"],$set["span_y"]);

						
						if($set["span_x"] > 0 || $set["span_y"] > 0){
							$cell_rect_row[] = [$l+$set["span_x"], $h+$set["span_y"], $col_width - $set["span_x"]*2,$this->y - $h - $set["span_y"] *2,$this->page];
						}
						$this->cell_rect[] = $cell_rect_row;
						$l += $col_width;					
					}

				}
			}
						
			//次の高さを指定
			$h = $this->page_max_height[$maxpage];

			//横を指定
			if(!isset($marginleft)){
				$l = $this->lMargin;
			}else{
				$l = $this->lMargin + $marginleft;
			}
			// set the $currpage to the last page
			$currpage = $maxpage;
			
		}
		
		// テーブルブレイク
		if(isset($set["tablebreak"])){
			if($saved_maxpage_table != $maxpage){

				$this->undo("table");
				$this->page = $saved_maxpage_table;
				$this->pagebreak_for_morepagestable();
				unset($set["tablebreak"]);
				$this->morepagestable($datas, $lineheight, $marginleft, $fill, $set);
				return;
			}
		}
		
		$flg_out_border=true;
		if($set["span_x"] >0 || $set["span_y"] > 0 || $this->LineWidth == 0){
			$flg_out_border=false;
		}

		// now we start at the top of the document and walk down
		if($flg_out_border){
			for($i = $startpage; $i <= $maxpage; $i++) {
				$this->page = $i;
				//$l = $this->lMargin;
				if(!isset($marginleft)){
					$l = $this->lMargin;
				}else{
					$l = $this->lMargin + $marginleft;
				}

				//高さ
				$lh = $this->page_max_height[$i];

				//背景
				if($i == $startpage){
					$t = $startheight;
				}else{
					$t = $this->tMargin;
				}
				if($fill){
					$save_y = $this->GetY();
					$this->Rect($l, $t, $fullwidth, $lh-$t, "DF");
					$this->SetY($save_y);
				}

				// 上のライン
				if($i == $startpage){
					$t = $startheight;
				}else{
					$t = $this->tMargin;
					//２ページ以降の一番上のライン
					if(strpos($set["table_border"],"H") !== false ){
						$this->Line($l,$t,$fullwidth+$l,$t);
					}
				}

				//横のライン
				if(!empty($tableline[$this->page])){
					foreach($tableline[$this->page] as $arr){
							if(strpos($set["table_border"],"H") !== false ){
								if (isset($arr[4])) {
									$this->Line($arr[0],$arr[1],$arr[2],$arr[3],$arr[4]);
								} else {
									$this->Line($arr[0],$arr[1],$arr[2],$arr[3]);
								}
							}
						}
				}

				// 下のライン
				if(strpos($set["table_border"],"H") !== false ){
					$this->Line($l,$lh,$fullwidth+$l,$lh);
				}

				// 縦ライン
				if(strpos($set["table_border"],"V") !== false ){
					$this->Line($l,$t,$l,$lh); // 一列目の縦ライン
					foreach($this->tablewidths AS $width) {
						$l += $width;
						// 二列目以降の縦ライン
						$this->Line($l,$t,$l,$lh);
					}
				}
			}
		}
		
		// セルのライン
		foreach($this->cell_rect as $cell_rect_row){
			$cell_h=0;
			foreach($cell_rect_row as $row){
				if($row[3] > $cell_h){
					// 高さの最大を設定
					$cell_h = $row[3];
				}
			}
			foreach($cell_rect_row as $row){
				$cell_x = $row[0];
				$cell_y = $row[1];
				$cell_w = $row[2];
				$this->page = $row[4];
				$save_x = $this->GetX();
				$save_y = $this->GetY();

				if($fill){
					if($this->LineWidth > 0){
						$this->Rect($cell_x, $cell_y, $cell_w, $cell_h,"DF");
					}else{
						$this->Rect($cell_x, $cell_y, $cell_w, $cell_h,"F");
					}
				}else{
					if($this->LineWidth > 0){
						$this->Rect($cell_x, $cell_y, $cell_w, $cell_h,"D");
					}
				}
				$this->SetXY($save_x, $save_y);
			}
		}
		
		//背景がある場合は再度文字を書き出す
		if($fill){
			foreach($this->filled_text as $arr){
				$this->page = $arr[7];
				$save_x = $this->GetX();
				$save_y = $this->GetY();
				$this->SetXY($arr[5], $arr[6]);
				$this->MultiCell($arr[0],$arr[1],$arr[2],$arr[3],$arr[4],false,$arr[8],$arr[9]);
				$this->SetXY($save_x, $save_y);
			}
		}
		
		// set it to the last page, if not it'll cause some problems
		$this->page = $maxpage;
		$this->SetY($this->page_max_height[$maxpage]);
		

	}

	
	/*
	 * Code 39
	 */
	function Code39($xpos, $ypos, $code, $baseline=0.5, $height=5, $align = 'L', $show_text = true, $wide_ratio = 3.0){

		$wide_ratio = (float) $wide_ratio;
		if ($wide_ratio <= 1.0) {
			$wide_ratio = 3.0;
		}
		$wide = $baseline;
		$narrow = $baseline / $wide_ratio;
		$gap = $narrow;

		$barChar['0'] = 'nnnwwnwnn';
		$barChar['1'] = 'wnnwnnnnw';
		$barChar['2'] = 'nnwwnnnnw';
		$barChar['3'] = 'wnwwnnnnn';
		$barChar['4'] = 'nnnwwnnnw';
		$barChar['5'] = 'wnnwwnnnn';
		$barChar['6'] = 'nnwwwnnnn';
		$barChar['7'] = 'nnnwnnwnw';
		$barChar['8'] = 'wnnwnnwnn';
		$barChar['9'] = 'nnwwnnwnn';
		$barChar['A'] = 'wnnnnwnnw';
		$barChar['B'] = 'nnwnnwnnw';
		$barChar['C'] = 'wnwnnwnnn';
		$barChar['D'] = 'nnnnwwnnw';
		$barChar['E'] = 'wnnnwwnnn';
		$barChar['F'] = 'nnwnwwnnn';
		$barChar['G'] = 'nnnnnwwnw';
		$barChar['H'] = 'wnnnnwwnn';
		$barChar['I'] = 'nnwnnwwnn';
		$barChar['J'] = 'nnnnwwwnn';
		$barChar['K'] = 'wnnnnnnww';
		$barChar['L'] = 'nnwnnnnww';
		$barChar['M'] = 'wnwnnnnwn';
		$barChar['N'] = 'nnnnwnnww';
		$barChar['O'] = 'wnnnwnnwn'; 
		$barChar['P'] = 'nnwnwnnwn';
		$barChar['Q'] = 'nnnnnnwww';
		$barChar['R'] = 'wnnnnnwwn';
		$barChar['S'] = 'nnwnnnwwn';
		$barChar['T'] = 'nnnnwnwwn';
		$barChar['U'] = 'wwnnnnnnw';
		$barChar['V'] = 'nwwnnnnnw';
		$barChar['W'] = 'wwwnnnnnn';
		$barChar['X'] = 'nwnnwnnnw';
		$barChar['Y'] = 'wwnnwnnnn';
		$barChar['Z'] = 'nwwnwnnnn';
		$barChar['-'] = 'nwnnnnwnw';
		$barChar['.'] = 'wwnnnnwnn';
		$barChar[' '] = 'nwwnnnwnn';
		$barChar['*'] = 'nwnnwnwnn';
		$barChar['$'] = 'nwnwnwnnn';
		$barChar['/'] = 'nwnwnnnwn';
		$barChar['+'] = 'nwnnnwnwn';
		$barChar['%'] = 'nnnwnwnwn';

		
		/*
		 * バーコードの長さを調べる
		 */
		$lencode = '*'.strtoupper($code).'*';
		$lineWidth = 0;
		for($i=0; $i<strlen($lencode); $i++){
			$char = $lencode[$i];
			$seq = $barChar[$char];
			for($bar=0; $bar<9; $bar++){
				if($seq[$bar] == 'n'){
					$lineWidth += $narrow;
				}else{
					$lineWidth += $wide;
				}
			}
			$lineWidth += $gap;
		}
		
		/*
		 * 右寄せの場合は、xの位置を変更する
		 */
		if($align != 'L'){
			$xpos = $xpos - $lineWidth;
		}

		if ($show_text) {
			$this->SetFontSize(10);
			$this->Text($xpos, $ypos + $height + 4, $code);
		}
		$this->SetFillColor(0);

		$code = '*'.strtoupper($code).'*';
		for($i=0; $i<strlen($code); $i++){
			$char = $code[$i];
			if(!isset($barChar[$char])){
				$this->Error('Invalid character in barcode: '. $code . ' ERROR:' . $char);
			}
			$seq = $barChar[$char];
			for($bar=0; $bar<9; $bar++){
				if($seq[$bar] == 'n'){
					$lineWidth = $narrow;
				}else{
					$lineWidth = $wide;
				}
				if($bar % 2 == 0){
					$this->Rect($xpos, $ypos, $lineWidth, $height, 'F');
				}
				$xpos += $lineWidth;
			}
			$xpos += $gap;
		}
	}
	
	function Rotate($angle,$x=-1,$y=-1)
	{
		if($x==-1)
			$x=$this->x;
		if($y==-1)
			$y=$this->y;
		if($this->angle!=0)
			$this->_out('Q');
		$this->angle=$angle;
		if($angle!=0)
		{
			$angle*=M_PI/180;
			$c=cos($angle);
			$s=sin($angle);
			$cx=$x*$this->k;
			$cy=($this->h-$y)*$this->k;
			$this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
		}
	}
	
	var $saved_pages; // undoするときの戻る地点
	
	function save($table_or_row){
		$this->saved_pages[$table_or_row]["page"] = $this->page;
		$this->saved_pages[$table_or_row]["pages"] = $this->pages;
		$this->saved_pages[$table_or_row]["tablewidths"] = $this->tablewidths;
		$this->saved_pages[$table_or_row]["page_max_height"] = $this->page_max_height;
		$this->saved_pages[$table_or_row]["filled_text"] = $this->filled_text;
		$this->saved_pages[$table_or_row]["cell_rect"] = $this->cell_rect;
	}

	function undo($table_or_row){
		$this->page = $this->saved_pages[$table_or_row]["page"];
		$this->pages = $this->saved_pages[$table_or_row]["pages"];
		$this->tablewidths = $this->saved_pages[$table_or_row]["tablewidths"];
		$this->page_max_height = $this->saved_pages[$table_or_row]["page_max_height"];
		$this->filled_text = $this->saved_pages[$table_or_row]["filled_text"];
		$this->cell_rect = $this->saved_pages[$table_or_row]["cell_rect"];
	}
	
}
