<?

if(!function_exists('sql_table')){ 
	function sql_table($name) { 
		return 'nucleus_' . $name; 
	}
}

class NP_HeelloWorld extends NucleusPlugin {

	// name of plugin
	function getName() {
		return 'Add item by mail'; 
	}
	
	// author of plugin
	function getAuthor() { 
		return 'ToR + ma + CLES'; 
	}
	
	// an URL to the plugin website
	// can also be of the form mailto:foo@bar.com
	function getURL() {
		return 'http://blog.cles.jp/blog/2'; 
	}
	
	// version of the plugin
	function getVersion() {
		return '0.8 + CLESv3'; 
	}
	function install() {
		$this->createMemberOption('enable','プラグインを有効にするか？','yesno','no');
		
		$this->createMemberOption('host','POP3 ホスト名','text','localhost');
		$this->createMemberOption('user','POP3 ユーザー名','text','');
		$this->createMemberOption('pass','POP3 パスワード','password','');
		
		$this->createMemberOption('blogid','Nucleus blog Id','text','1');
		$this->createMemberOption('categoryname','Nucleus カテゴリ名','text','CategoryName');
		$this->createMemberOption('imgonly','イメージ添付メールのみ追加？','yesno','no');
		$this->createMemberOption('DefaultPublish','デフォルトで公開するか？','yesno','no');
		
		$this->createMemberOption('optionsKeyword','オプション記述開始の区切り文字','text','@');
		$this->createMemberOption('blogKeyword','オプションでblogidを指定する場合のキー','text','b');
		$this->createMemberOption('categoryKeyword','オプションでカテゴリを指定する場合のキー','text','c');
		$this->createMemberOption('publishKeyword','オプションでストレートにpublish指定する場合のキー','text','s');
		
		$this->createMemberOption('accept','投稿許可アドレス（複数の場合改行で区切ってください）','textarea','');
		$this->createMemberOption('nosubject','件名がないときの題名','text','');
		$this->createMemberOption('no_strip_tags','htmlメールの場合に除去しないタグ','textarea','<title><hr><h1><h2><h3><h4><h5><h6><div><p><pre><sup><ul><ol><br><dl><dt><table><caption><tr><li><dd><th><td><a><area><img><form><input><textarea><button><select><option>');
		$this->createMemberOption('maxbyte','最大添付量(B)','text','300000');
		$this->createMemberOption('subtype','対応MIMEタイプ(正規表現)','text','gif|jpe?g|png|bmp|octet-stream|x-pmd|x-mld|x-mid|x-smd|x-smaf|x-mpeg');
		$this->createMemberOption('viri','保存しないファイル(正規表現)','text','.+\.exe$|.+\.zip$|.+\.pif$|.+\.scr$');
		
		$this->createMemberOption('thumb_ok','サムネイルを使用する？','yesno','yes');
		$this->createMemberOption('W','サムネイルの大きさ(Width)','text','120');
		$this->createMemberOption('H','サムネイルの大きさ(Hight)','text','120');
		$this->createMemberOption('thumb_ext','サムネイルを作る対象画像','text','.+\.jpe?g$|.+\.png$');
		$this->createMemberOption('smallW','アイテム内に表示する画像の最大横幅','text','120');
		
		$this->createOption('debug','ログを出力する？','yesno','no');
	}
	
	// a description to be shown on the installed plugins listing
	function getDescription() { 
		return 'メールを拾ってアイテムを追加します。&lt;%plugin(HeelloWorld)%&gt;の記述のあるスキンを適用するページを開くと実行されます。<br />個人ごとに設定ができるようになりましたので「あなたの設定」か「メンバー管理」から設定を行ってください。';
	}
	
	function supportsFeature($what) { 
		switch($what) { 
			case 'SqlTablePrefix': 
				return 1; 
			default: 
				return 0; 
		}
	}

	function init(){
	}
	
	function _log($msg){
		if( $this->getOption('debug') == 'yes' ){
			ACTIONLOG::add(WARNING, 'HeelloWorld: ' . $msg);
		}
	}
	
	function _getEnableUserId(){
		$userOptions = $this->getAllMemberOptions('enable');
		
		$userIds = Array();
		$query =  'SELECT * FROM '.sql_table('member');
		$res = sql_query($query);
		$numrows = mysql_num_rows($res);
		if ($numrows == 0)
			return 0;
		while($assoc = mysql_fetch_assoc($res)){
			$userId = $assoc['mnumber'];
			if( $userOptions[$userId] == 'yes' ){
				$userIds[] = $userId;
				$this->_log("Enable user found : $userId");
			}
		}
		mysql_free_result($res);
		return $userIds;
	}
	
	function _initMediaDirByUserId($userId){
		global $member, $DIR_MEDIA;
		$this->_log("ユーザ($userId)の初期設定");
		
		/* ***
			写メールBBS by ToR 2002/09/25
			http://php.s3.to
			↑こちらのメールチェックスクリプトを一部流用しています
		*/
		
		/*-- 受信メールサーバーの設定--*/
		//メールのホスト
		$this->host = $this->getMemberOption($userId, 'host');
		// ユーザーID
		$this->user = $this->getMemberOption($userId, 'user');
		// パスワード
		$this->pass = $this->getMemberOption($userId, 'pass');
		
		/*-- アイテムを追加するNucleus情報のデフォルト--*/
		// メールでアイテムを追加するblogのID
		$this->blogid = $this->getMemberOption($userId, 'blogid');
		// メールでアイテムを追加するカテゴリの名前
		$this->categoryname = $this->getMemberOption($userId, 'categoryname');
		// 添付メールだけを記録する？Yes=1 No=0（Noの時はすべてのメールを追加）
		$this->imgonly = ( $this->getMemberOption($userId, 'imgonly') == 'yes' ) ? 1 : 0 ;
		// 直接blogにアイテムを追加する？Yes=1 No=0（Noの時はドラフト追加）
		$this->DefaultPublish = ( $this->getMemberOption($userId, 'DefaultPublish') == 'yes' ) ? 1 : 0 ;
		
		/*-- メールのタイトルに各種オプションを含める場合の設定--*/
		// オプション記述開始の区切り文字
		$this->optionsKeyword = $this->getMemberOption($userId, 'optionsKeyword');
		// オプションでblogidを指定する場合のキー (小文字で指定、メール入力は大文字でOK)
		$this->blogKeyword = $this->getMemberOption($userId, 'blogKeyword');
		// オプションでカテゴリを指定する場合のキー (小文字で指定、メール入力は大文字でOK)
		$this->categoryKeyword = $this->getMemberOption($userId, 'categoryKeyword');
		// オプションでストレートにpublish指定する場合のキー (小文字で指定、メール入力は大文字でOK)
		$this->publishKeyword = $this->getMemberOption($userId, 'publishKeyword');	//ストレートのsです。
		
		// 投稿許可アドレス（ログに記録する）
		$this->accept = explode("\n",$this->getMemberOption($userId, 'accept'));
		$this->accept = Array_Map("Trim", $this->accept);
		// 件名がないときの題名
		$this->nosubject = $this->getMemberOption($userId, 'nosubject');
		// htmlメールの場合に除去しないタグ
		//そのままアイテム本文に記録されますが、blogの設定が改行文字置換onの場合は再編集で<br />
		$this->no_strip_tags = $this->getMemberOption($userId, 'no_strip_tags');

		// 最大添付量（バイト・1ファイルにつき）※超えるものは保存しない
		$this->maxbyte = $this->getMemberOption($userId, 'maxbyte');
		// 対応MIMEタイプ（正規表現）Content-Type: image/jpegの後ろの部分。octet-streamは危険かも
		$this->subtype = $this->getMemberOption($userId, 'subtype');
		// 保存しないファイル(正規表現)
		$this->viri = $this->getMemberOption($userId, 'viri');
		/*-- サムネイル--*/
		//サムネイルを使用する？ Yes=1 No=0(GDライブラリ利用不可の場合は自動判定)
		$this->thumb_ok = ( $this->getMemberOption($userId, 'thumb_ok') == 'yes' ) ? 1 : 0 ;
		//サムネイルの大きさ(これ以上の大きい画像はjpg,pngのサムネイル作成)
		$this->W = $this->getMemberOption($userId, 'W');
		$this->H = $this->getMemberOption($userId, 'H');
		//サムネイルを作る対象画像(サムネイルを作成しない場合は、値を空にしてください)
		$this->thumb_ext = $this->getMemberOption($userId, 'thumb_ext');
		/*-- サムネイル作成できない(orしない)場合)--*/
		//アイテム内に表示する画像の最大横幅(imgタグ内のwidthの値)
		$this->smallW = $this->getMemberOption($userId, 'smallW');
		/*-- ここから先は自動生成--*/
		
		// ★画像保存ディレクトリ
		$this->_log($DIR_MEDIA."<br />\n");
		$this->memid = $userId;
		
		$this->tmpdir  = $DIR_MEDIA.$this->memid.'/';
		if(!is_writable($this->tmpdir)){
			$this->_log("設定エラー:medeia/".$this->memid."/ディレクトリが存在しないか、書き込み可能になっていません");
		}
		
		//★サムネイル保存ディレクトリ
		$this->thumb_dir  = $DIR_MEDIA.$this->memid.'/';
	}
	
	// コマンドー送信！！
	function _sendcmd($sock,$cmd) {
		fputs($sock, $cmd."\r\n");
		$buf = fgets($sock, 512);
		if(substr($buf, 0, 3) == '+OK') {
		return $buf;
		} else {
			$this->_log("_sendcmd: $buf");
			die("$cmd => $buf");
		}
		return false;
	}

	/* ヘッダと本文を分割する */
	function mime_split($data) {
		$part = split("\r\n\r\n", $data, 2);
		$part[1] = ereg_replace("\r\n[\t ]+", " ", $part[1]);
		return $part;
	}

	/* メールアドレスを抽出する */
	function addr_search($addr) {
		if (eregi("[-!#$%&\'*+\\./0-9A-Z^_`a-z{|}~]+@[-!#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+", $addr, $fromreg)) {
			return $fromreg[0];
		} else {
			return false;
		}
	}
	
	/* 文字コードコンバート*/
	function convert($str) {
		return mb_convert_encoding($str, _CHARSET, "ISO-2022-JP,ASCII,JIS,UTF-8,EUC-JP,SJIS"); 
	}
	
	function parseToken($line,$keyword) {
		$words = explode($keyword,$line);
		$words = explode('=',$words[1]);
		$word = explode('&',$words[1]); 
		return $word[0];
	}

	function thumb_create($src, $W, $H, $thumb_dir="./"){
		// 画像の幅と高さとタイプを取得
		$size = GetImageSize($src);
		switch ($size[2]) {
			case 1 : return false; break;
			case 2 : $im_in = @ImageCreateFromJPEG($src); break;
			case 3 : $im_in = ImageCreateFromPNG($src);  break;
		}
		if (!$im_in) die("GDをサポートしていないか、ソースが見つかりません<br>phpinfo()でGDオプションを確認してください");
		// リサイズ
		if ($size[0] > $W || $size[1] > $H) {
			$key_w = $W / $size[0];
			$key_h = $H / $size[1];
			($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;
			$out_w = $size[0] * $keys;
			$out_h = $size[1] * $keys;
		} else {
			$out_w = $size[0];
			$out_h = $size[1];
		}
		// 出力画像（サムネイル）のイメージを作成し、元画像をコピーします。(GD2.0用)
		$im_out = ImageCreateTrueColor($out_w, $out_h);
		$resize = ImageCopyResampled($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
		
		// サムネイル画像をブラウザに出力、保存
		$filename = substr($src, strrpos($src,"/")+1);
		$filename = substr($filename, 0, strrpos($filename,"."));
		$this->thum_filename = $filename . "-small.jpg";
		ImageJPEG($im_out, $this->thumb_dir.$this->thum_filename);	//jpgサムネイル作成
		// 作成したイメージを破棄
		ImageDestroy($im_in);
		ImageDestroy($im_out);
	}
	
	function doSkinVar($skinType) {
		$enabledUserIds = $this->_getEnableUserId();		
		foreach($enabledUserIds as $userId) {
			$this->_log("ユーザ($userId)のメールを取得開始します");
			$this->blogByMail($userId);
			$this->_log("ユーザ($userId)のメールを取得終了しました");
		}
	}	//end of function doSkinVar($skinType)
	
	function blogByMail($userId){
		$this->_initMediaDirByUserId($userId);
		
		global $manager, $blog, $CONF;
		$sock = fsockopen($this->host, 110, $err, $errno, 10) or die("サーバーに接続できません");
		$buf = fgets($sock, 512);
		if(substr($buf, 0, 3) != '+OK') die($buf);
		
		$buf = $this->_sendcmd($sock, "USER $this->user");
		$buf = $this->_sendcmd($sock, "PASS $this->pass");
		$this->_log($buf);
		$data = $this->_sendcmd($sock, "STAT");//STAT -件数とサイズ取得 +OK 8 1234
		sscanf($data, '+OK %d %d', $num, $size);
		$this->_log($data);
		
		if ($num == "0") {
			$buf = $this->_sendcmd($sock, "QUIT"); //バイバイ
			fclose($sock);
			$this->_log("no mail");
		} else { // メールがある時の処理
			// 件数分
			for($i=1;$i<=$num;$i++) {
				$line = $this->_sendcmd($sock, "RETR $i");//RETR n -n番目のメッセージ取得（ヘッダ含）
				while (!ereg("^\.\r\n",$line)) {//EOFの.まで読む
					$line = fgets($sock,512);
					$dat[$i].= $line;
				}
				$data = $this->_sendcmd($sock, "DELE $i");//DELE n n番目のメッセージ削除
			}
			$buf = $this->_sendcmd($sock, "QUIT"); //バイバイ
			fclose($sock);
			
			for($j=1;$j<=$num;$j++) {	//メールの件数分ループで内容を取り出す
				$write = true;
				$subject = $from = $text = $atta = $part = $attach = "";
				list($head, $body) = $this->mime_split($dat[$j]);	//ヘッダと本文を分割する
				
				$this->_log("\$head:<br />\n".$head."<br />\n");
				$this->_log("\$body:<br />\n".$body."<br />\n");
				
				// 日付の抽出----------
				eregi("Date:[ \t]*([^\r\n]+)", $head, $datereg);
				$now = strtotime($datereg[1]);
				if ($now = -1) $now = time();	//1061340376
				$this->_log("\$now:<br />\n".$now."<br />\n");
				
				// サブジェクトの抽出
				if (eregi("\nSubject:[ \t]*([^\r\n]+)", $head, $subreg)) {
					$subject = $subreg[1];
					while (eregi("(.*)=\?iso-2022-jp\?B\?([^\?]+)\?=(.*)",$subject,$regs)) {//MIME Bデコード
						$subject = $regs[1].base64_decode($regs[2]).$regs[3];
					}
					while (eregi("(.*)=\?iso-2022-jp\?Q\?([^\?]+)\?=(.*)",$subject,$regs)) {//MIME Bデコード
						$subject = $regs[1].quoted_printable_decode($regs[2]).$regs[3];
					}
					$subject = htmlspecialchars($this->convert($subject));
					$this->_log("◆題名そのまま\$subject:<br />\n".$subject."<br />\n");
					//オプション分割
					if (preg_match('/'.$this->optionsKeyword.'/', $subject)) {
						list($subject,$option) = spliti($this->optionsKeyword,$subject,2);
						$this->_log("◆オプション除いた\$subject:<br />\n".$subject."<br />\n");
						$this->_log("◆オプション抽出\$option:<br />\n".$option."<br />\n");
						$option = strtolower($option);
						$this->_log("◆変換後\$option:<br />\n".$option."<br />\n");
						
						if (preg_match('/'.$this->blogKeyword.'/', $option)) {
							$this->blogid = $this->parseToken($option,$this->blogKeyword);
						}	
						if (preg_match('/'.$this->categoryKeyword.'/', $option)) {
							$this->categoryname = $this->parseToken($option,$this->categoryKeyword);
						}
						if (preg_match('/'.$this->publishKeyword.'/', $option)) {
							$this->DefaultPublish = $this->parseToken($option,$this->publishKeyword);
						}
						
					}
				}
				
				// 送信者アドレスの抽出
				if (eregi("From:[ \t]*([^\r\n]+)", $head, $freg)) {
					$from = $this->addr_search($freg[1]);
				} elseif (eregi("Reply-To:[ \t]*([^\r\n]+)", $head, $freg)) {
					$from = $this->addr_search($freg[1]);
				} elseif (eregi("Return-Path:[ \t]*([^\r\n]+)", $head, $freg)) {
					$from = $this->addr_search($freg[1]);
				}
				$this->_log("\$from:<br />\n".$from."<br />\n");
				
				// 受付アドレス
				$from = Trim($from);
				if (in_array ($from, $this->accept)) {
					$this->_log("受け付けます($from)");
				}else{
					$this->_log("拒否します($from)");
					$write = false;
				}
				
				// マルチパートならばバウンダリに分割
				if (eregi("\nContent-type:.*multipart/",$head)) {
					eregi('boundary="([^"]+)"', $head, $boureg);
					$body = str_replace($boureg[1], urlencode($boureg[1]), $body);
					$part = split("\r\n--".urlencode($boureg[1])."-?-?",$body);
					if (eregi('boundary="([^"]+)"', $body, $boureg2)) { //multipart/altanative
						$body = str_replace($boureg2[1], urlencode($boureg2[1]), $body);
						$body = eregi_replace("\r\n--".urlencode($boureg[1])."-?-?\r\n","",$body);
						$part = split("\r\n--".urlencode($boureg2[1])."-?-?",$body);
					}
				} else {
					$part[0] = $dat[$j];// 普通のテキストメール
				}
				
				foreach ($part as $multi) {
					list($m_head, $m_body) = $this->mime_split($multi);
					$m_body = ereg_replace("\r\n\.\r\n$", "", $m_body);
					if (!eregi("Content-type: *([^;\n]+)", $m_head, $type)) continue;
					list($main, $sub) = explode("/", $type[1]);
					$this->_log("<br />\$type[1]: $type[1],\$sub: $sub<br />");
					
					// 本文をデコード
					if (strtolower($main) == "text") {
						if (eregi("Content-Transfer-Encoding:.*base64", $m_head)) 
							$m_body = base64_decode($m_body);
						if (eregi("Content-Transfer-Encoding:.*quoted-printable", $m_head)) 
							$m_body = quoted_printable_decode($m_body);
						$text = $this->convert($m_body);
						$text = strip_tags($text,$this->no_strip_tags);
						
						$blog = new BLOG($this->blogid);
						if( $blog->getSetting('bconvertbreaks') ){	//blog設定で改行を<br />に置換onの場合
							if ($sub == "html"){	//改行文字を削除、<br>タグを\nへ
								$text = str_replace("\r\n", "\r",$text);
								$text = str_replace("\r", "\n",$text);
								$text = str_replace("\n", "", $text);
								$text = str_replace("<br>", "\n", $text);
							}
						}
					}
					
					// ファイル名を抽出
					if (eregi("name=\"?([^\"\n]+)\"?",$m_head, $filereg)) {
						$filename = trim($filereg[1]);
						if (eregi("(.*)=\?iso-2022-jp\?B\?([^\?]+)\?=(.*)",$filename,$regs)) {
							$filename = $regs[1].base64_decode($regs[2]).$regs[3];
							$filename = $this->convert($filename);
						}
						$filename = time() . "-".$filename;
					}
					
					$this->_log($filename);
					
					// 添付データをデコードして保存
					if (eregi("Content-Transfer-Encoding:.*base64", $m_head) && eregi($this->subtype, $sub)) {
						$this->_log("書き込み開始");
						$tmp = base64_decode($m_body);
						if (!$filename) $filename = time().".$sub";
						if (strlen($tmp) < $this->maxbyte && !eregi($this->viri, $filename)) {
							
							$fp = fopen($this->tmpdir.$filename, "w");
							fputs($fp, $tmp);
							fclose($fp);
							
							$link = rawurlencode($filename);
							$attach = $filename;
							$this->_log("\$attach:<br />". $attach);
							
							//サムネイル
							$size = getimagesize($this->tmpdir.$filename);
							
							if($this->thumb_ok&& function_exists('ImageCreate')) {	//サムネイル作成する場合
								if (preg_match("/$this->thumb_ext/i",$filename)) {	//サムネイル作成する拡張子の場合
									if ($size[0] > $this->W || $size[1] > $this->H) {
										$this->thumb_create($this->tmpdir.$filename,$this->W,$this->H,$this->thumb_dir);
									}
								}
							}
							if(! @getimagesize($this->thumb_dir.$this->thum_filename))
								$this->thumb_ok = 0;
						} else {
							$write = false;
						}
					}	//end 添付データをデコードして保存
				}	//end foreach
				if ($this->imgonly && $attach==""){
					$this->_log("添付ファイルがないので書き込みません");
					$write = false;
				}
				if(trim($subject)==""){	//題名がない場合
					$subject = $this->nosubject;
				}
					
				if ($attach==""){
					$body = $text;
				}else{	//添付ファイルがある場合の本文のソース
					if($this->thumb_ok){	//サムネイルがある場合のソース
						$thumb_size = getimagesize($this->thumb_dir.$this->thum_filename);
						$body = '<div class="leftbox"><a href="'.$CONF['MediaURL'].$this->memid.'/'.$attach.'" target="_blank"><%image('.$this->memid.'/'.$this->thum_filename.'|'.$thumb_size[0].'|'.$thumb_size[1].'|)%></a></div>'.$text;
					}else{	//サムネイルがない場合のソース
						if( $size[0] > $this->smallW){	//縮小表示
							$smallH = round($this->smallW / $size[0] * $size[1] , 0);
							$body = '<div class="leftbox"><a href="'.$CONF['MediaURL'].$this->memid.'/'.$attach.'" target="_blank"><%image('.$this->memid.'/'.$attach.'|'.$this->smallW.'|'.$smallH.'|)%></a></div>'.$text;
						}else{	//そのまま表示
							$body = '<div class="leftbox"><%image('.$this->memid.'/'.$attach.'|'.$size[0].'|'.$size[1].'|)%></a></div>'.$text;
						}
					}
				}
				if ($write) {
					$this->_log("アイテム追加します！！！！！！！！！");
					$subject = $this->convert($subject);
					$body = $this->convert($body);
					$timestamp = $blog->getCorrectTime();
					$more = "";
					
					$this->_addDatedItem($this->blogid,
							$subject,
							$body,
							$more,
							0,
							$timestamp,
							0,
							$this->categoryname);
				}
				//======================
			}	//end 件数分内容を取り出す
		}	//end メールがある時の処理
	}	//end of function blogByMail()

	function _addDatedItem($blogid, $title, $body, $more,$closed, $timestamp, $future, $catname = "") {
		// 1. ログイン======================
		$mem = MEMBER::createFromID($this->memid);
		
		// 2. ブログ追加できるかチェック======================
		if (!BLOG::existsID($this->blogid)) {
			$this->_log("存在しないblogです");
			return ;
		} else {
			$this->_log("blogidはOK!");
		}
		if (!$mem->teamRights($blogid)) {
			$this->_log( "メンバーではありません");
			return;
		} else {
			$this->_log("メンバーチェックもok!");
		}
		if (!trim($body)){
			$this->_log("空のアイテムは追加できません");
			return;
		} else {
			$this->_log("アイテムは空じゃないです");
		}
		
		// 3. 値の補完======================
		$blog = new BLOG($this->blogid);
		// カテゴリID ゲット (誤ったカテゴリID使用時はデフォを使用)
		$catid = $blog->getCategoryIdFromName($catname);
		$this->_log("追加するcatid" . $catid );
		if ( $this->DefaultPublish ){
			$draft = 0;
		} else {
			$draft = 1;	//ドラフト追加
			$this->_log("ドラフトで追加します");
		}
		if ($closed != 1)
			$closed = 0;	//コメントを許可
		$this->_log("\$catid:".$catid."|\$draft:".$draft."\$closed:".$closed);
		
		// 4. blogに追加======================
		$store = $blog->getSetting('bconvertbreaks');
		// htmlメールの場合は改行タグを入れない
		if ( $this->type == "html"){ 
			$blog->setConvertBreaks(0); 
		}
		$itemid = $blog->additem($catid, $title, $body, $more, $blogid, $mem->getID(), $timestamp, $closed, $draft);
		$this->_log($itemid);
		
		$blog->setConvertBreaks($store);
	}
}
?>
