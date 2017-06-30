<?php
//Define the required constants
require_once('config.php');
define('ROOT_DIR', realpath(dirname(__FILE__)) .'/');
define('CONTENT_DIR', ROOT_DIR . CONTENT_SUBDIR);

class mfcms{
	public static $content = '';//The content of this page
	public static $params;//The parameters of the config tag
	public static $file = '';//The file currently opened (full path)
	public static $menu = '';//The menu string
	public static $footer = '';//The footer thet will show available actions
	public static $pf = '';//Will hold the content of $_GET['f'], will always be set
	public static $islogged = false;//Will store if the useris logged
	public static $exitheader = "<?php die(); ?>".PHP_EOL;//To kill a file that should not be accessed
	//Initializes the cms, it fills the static variables with the informations about this page, so we can acces this information with special functions
	public static function init(){
		$rwparams = "a=href,area=href,frame=src,input=src,form=";
		ini_set("url_rewriter.tags",$rwparams);
		ini_set("session.trans_sid_tags",$rwparams);
		//output_add_rewrite_var('um','dois');
		
		if(isset($_GET['f'])) self::$pf = $_GET['f'];
		self::sanitize(self::$pf); //may kill the script
		self::$islogged = self::verify_login();//Look the cookies to verify if the user is logged
		if((USE_HTACCESS == true) && (!file_exists('.htaccess'))) self::generate_htaccess(); //Generate the .htaccess file if not exists and we use it
		//Get the full path for the file that needs to be opened
		self::$file = self::get_file_name_to_open(self::$pf, true);
		self::sanitize(self::$file); //may kil the script
		self::$footer = self::create_footer();//Creates the footerm that will be divfferent if the user is logged in.
		if (!isset($_REQUEST["action"])){//the action parameter mean that instead of showing a file, we will show an internal page
			self::$content = self::get_file_to_show(self::$file); //Get the text of this page
			self::$params = self::parse_params(self::$content);//Now we have an associative array of the parameterss
			if(self::$islogged == true){//Tasks to run only when logged
				if(isset($_SESSION['return'])){//The return session should not exist when we are outside an action, when inside, 
					unset($_SESSION['return']);//The action will decide what to do with this
				}
			}
		}else{
			$action = self::load_action($_REQUEST["action"]); //The action will return an associative array with "content" and "params"
			self::$content = $action["content"];
			self::$params = $action["params"];
		}
		self::$menu = self::create_menu_string();
	}
	//Generates the .htaccess file
	public static function generate_htaccess(){
		file_put_contents('.htaccess','<IfModule mod_rewrite.c>'.PHP_EOL.
		'RewriteEngine On'.PHP_EOL.
		'RewriteCond	%{DOCUMENT_ROOT}'.ROOT_URL.CONTENT_SUBDIR.'$1 !-f'.PHP_EOL.
		'RewriteCond %{REQUEST_FILENAME} !-f'.PHP_EOL.
		'RewriteCond %{REQUEST_FILENAME} !-d'.PHP_EOL.
		'RewriteRule ^(.*)$ index.php?f=$1&%{QUERY_STRING} [L]'.PHP_EOL.PHP_EOL.
		'RewriteCond %{DOCUMENT_ROOT}'.ROOT_URL.CONTENT_SUBDIR.'$1 -f'.PHP_EOL.
		'RewriteRule ^(.*)$ '.CONTENT_SUBDIR.'$1 [L]'.PHP_EOL.
		'</IfModule>'.PHP_EOL.PHP_EOL.
		'Options -Indexes');
	}
	//This function transform $request, in the full path for the file that needs to be opened, $request is a relative path
	public static function get_file_name_to_open($request, $redirect){//$redirect enables redirecting to correct the path, because directories should end with slash and files shoud end without slashes
		$path = CONTENT_DIR . $request;
		$do_redirect = true;//Will it do the redirect
		//If the path is a directory, it should end with slash
		if(is_dir($path)){
			if(substr($path,-1,1) != "/") $request = $request . '/'; else $do_redirect = false;
			$path = $path.'index';//If the path is a directory, open the index
		}else{//If the path is not a directory, it should not end with slash
			if(substr($path,-1,1) == "/") $request = substr($request,0,strlen($request)-1); else $do_redirect = false;
		}
		//Redirect to the right place
		if(($redirect == true) && ($do_redirect == true)){
			if((USE_HTACCESS == false)) $request = '?f=' . urlencode($request);
			header('Location: '.ROOT_URL . $request);
			exit();
		}
		$path = $path.'.htmx';
		return $path;
	}
	//This will kill the cms if somebody tries to access a parent directory
	public static function sanitize($text){
		if ((isset($text)) && (strpos($text, '..') !== false)) {
			header ("HTTP/1.0 403 Forbidden"); 
			exit; 
		}
	}
	//Gets the content of the given file or the 404 file
	public static function get_file_to_show($arq){
		if(file_exists($arq)) $ret = file_get_contents($arq);
		else{
			$ret = file_get_contents(CONTENT_DIR .'404.htmx');
			http_response_code(404);
		}
		return $ret;
	}
	//This function parses the special comment tags on the file and returns an associative array of this parameters
	public static function parse_params($text){
		$rtrn = Array();
		preg_match_all('/<\!--(.*\:.*)-->/', $text, $matches);
		foreach($matches[1] as $i){
			$spl = explode(":",$i,2);
			$rtrn[trim($spl[0])] = trim($spl[1]);
		}
		return $rtrn;
	}
	//This function creates a string with an ul menu
	public static function create_menu_string(){
		$menucachefile = ROOT_DIR . 'menu_cache.htmx';
		//The menu creation is a process that reads all files, so we use a cache to make things faster
		if (file_exists($menucachefile)){
			return file_get_contents($menucachefile);
		}else{
			mfcms_menucreator::createMenu('');//This create an array with needed info, more details on mfcms_menucreator class
			$menuItens = mfcms_menucreator::$menuItens;//This is the array with the needed info
			
			$menuDraft = new mfcms_ul('');
			foreach($menuItens as $link){
				//Links to index should go to the directory
				if (preg_match('/\/index$|^index$/',$link["url"])) $link["url"] = substr($link["url"],0,strlen($link["url"])-5);
				//If we don't use htaccess, we need to send the f parameter
				if((USE_HTACCESS == false) && ($link["url"] != '')) $link["url"] = '?f=' . urlencode($link["url"]);
				$itemlink = ROOT_URL . $link["url"];
				$add = new mfcms_li($itemlink, $link["text"],$link["position"], $link["nolink"]);
				$menuDraft->addel($add);
			}
			$menudata = $menuDraft->printText();
			//Create the cache
			file_put_contents($menucachefile,$menudata);
			return $menudata;
		}
	}
	//Will show the correct link to this page observing if we use htaccess
	public static function get_this_page($cont){//The parameter tells if we want to continue the query string
		$this_page = '';
		if((USE_HTACCESS == false) && (self::$pf !== '')){//The parameter f= must be explicit if we don't use htaccess'
			$this_page = '?f=' . urlencode(self::$pf);
		}else{
			$this_page = self::$pf;
		}
		if ($cont == true){//Put the correct char to continue the query string
			if((USE_HTACCESS == false) && (self::$pf !== '')){
				$this_page .= '&';
			}else{
				$this_page .= '?';
			}
		}
		return ROOT_URL.$this_page;
	}
	//This function verify if the user is logged, it runs everytile that the page is loaded
	public static function verify_login(){
		if(isset($_COOKIE['loginId'])){//Verify if login cookie exists
			if(file_exists(LOGIN_ID_FILE)){
				$localId = self::get_line(1,LOGIN_ID_FILE);
				if($_COOKIE['loginId'] == $localId){//Compare the cookie id with the id on disk
					session_start();
					return true;
				}
			}
			if((!file_exists(LOGIN_ID_FILE) or ($_COOKIE['loginId'] != $localId))){//Remove the cookie if it is invalid
				unset($_COOKIE['loginId']);
				setcookie("loginId", "", time()-3600, ROOT_URL);
			}
		}
		if((isset($_GET['LOGINID'])) or (isset($_POST['LOGINID']))){//Verify if login session exists
			if(file_exists(LOGIN_ID_FILE_NC)){
				$localId = self::get_line(1,LOGIN_ID_FILE_NC);
				if($_REQUEST['LOGINID'] == $localId){//Compare the session id with the id on disk
					ini_set("session.use_only_cookies", false);//We will use sessions to store some data
					ini_set("session.use_trans_sid", true);
					session_start();
					self::log_user_in_without_cookies();//We must change the id at every refresh
					return true;
				}
			}
		}
		return false;
	}
	//Gets the url to the previous page, using the session variable return to return to the file manager
	public static function get_return_url(){
		if (isset($_SESSION['return'])){
			return self::get_this_page(true).'action=filemanager&dir='.urlencode($_SESSION['return']);
		}else{
			return self::get_this_page(false);
		}
	}
	//This function logs the user in
	public static function log_user_in($remember){
		$loginId = md5(uniqid(rand(), true));//A random id that will be on cookie and on the server
		file_put_contents(LOGIN_ID_FILE, self::$exitheader.$loginId);
		if($remember == true){
			$expire = time()+7*24*60*60;
		}else{
			$expire = 0;
		}
		setcookie('loginId', $loginId, $expire, ROOT_URL);
	}
	//This function logs the user in without cookies
	public static function log_user_in_without_cookies(){
		$loginId = md5(uniqid(rand(), true));//A random id that will be on cookie and on the server
		file_put_contents(LOGIN_ID_FILE_NC, self::$exitheader.$loginId);
		output_add_rewrite_var('LOGINID',$loginId);
	}
	//This function does logout
	public static function logout(){
		unset($_COOKIE['loginId']);
		setcookie("loginId", "", time()-3600, ROOT_URL);
		if(is_file(LOGIN_ID_FILE)){
			unlink(LOGIN_ID_FILE);
		}
		if(is_file(LOGIN_ID_FILE_NC)){
			unlink(LOGIN_ID_FILE_NC);
		}
		if(is_file(TOKEN_FILE)){
			unlink(TOKEN_FILE);
		}
	}
	//This function removes indentation from html string, is a port from the javascript function
	public static function uglify($html){
		$outhtml = '';
		$insidetag = false;
		$insidepre = false;
		$inside1q = false;
		$inside2q = false;
		$lastchar = '';
		for($i = 0; $i < strlen($html); $i++){
			if(($html{$i} == '>') and ($inside1q != true) and ($inside2q != true)){
				$insidetag = false;
			}
			if($html{$i} == '<'){
				$insidetag = true;
				if(strtoupper(substr($html,$i+1,3)) == 'PRE'){
					$insidepre = true;
				}
				if(strtoupper(substr($html,$i+1,4)) == '/PRE'){
					$insidepre = false;
				}
			}
			if($insidetag == true){
				if($html{$i} == '"'){
					$inside2q = !$inside2q;
				}
				if($html{$i} == "'"){
					$inside1q = !$inside1q;
				}
			}
			$nextchar = $html{$i};
			$copythis = true;
			if(($insidetag != true) and ($insidepre != true) and ($inside1q != true) and ($inside2q != true)){
				if(preg_match('/[\s\n\t\r]/',$html{$i})){
					$nextchar = ' ';
					while(($i < strlen($html) - 1) and (preg_match('/[\s\n\t\r]/',$html{$i + 1}))){
						$i++;
					}
				}
			}
			$outhtml .= $nextchar;
		}
		return $outhtml;
	}
	//This function adds indentation to the html string, is a port from the javascript function
	public static function prettify($html){
		$outhtml = '';
		$insidetag = false;
		$insidepre = false;
		$insideEnd = false;
		$isexclude = false;
		$tabcount = 0;
		$exclude = ['a','b','big','i','small','tt','abbr','acronym','cite','code','dfn','em','kbd','strong','samp','time','var','bdo','br','img','map','object','q','script','span','sub','button','input','label','select','textarea','!--'];
		$inside1q = false;
		$inside2q = false;
		for($i = 0; $i < strlen($html); $i++){
			if($insidetag == true){
				if($html{$i} == '"'){
					$inside2q = !$inside2q;
				}
				if($html{$i} == "'"){
					$inside1q = !$inside1q;
				}
			}
			if(($html{$i} == '<') and ($inside1q != true) and ($inside2q != true) and ($insidepre != true)){
				$insidetag = true;
				for($k = 0; $k < count($exclude); $k++){
					if(
						((strtoupper(substr($html, $i + 1, strlen($exclude[$k]) + 1)) == strtoupper($exclude[$k]).' ')) or
						((strtoupper(substr($html, $i + 1, strlen($exclude[$k]) + 1)) == strtoupper($exclude[$k]).'>')) or
						((strtoupper(substr($html, $i + 1, strlen($exclude[$k]) + 2)) == '/'.strtoupper($exclude[$k]).' ')) or
						((strtoupper(substr($html, $i + 1, strlen($exclude[$k]) + 2)) == '/'.strtoupper($exclude[$k]).'>'))
					){
						$isexclude = true;
					}
				}
				if($isexclude == false){
					if($html{$i + 1} == '/'){
						$insideEnd = true;
						$tabcount--;
						$outhtml .= PHP_EOL;
						for($j = 0; $j < $tabcount; $j++){
							$outhtml .= '  ';
						}
					}else{
						$insideEnd = false;
						$tabcount++;
						$outhtml .= PHP_EOL;
						for($j = 0; $j < $tabcount; $j++){
							$outhtml .= '  ';
						}
					}
				}
			}
			
			$outhtml .= $html{$i};
			if(($html{$i} == '>') and ($inside1q != true) and ($inside2q != true) and ($insidepre != true)){
				$insidetag = false;
				if($isexclude == false){
					if($insideEnd == true){
						$tabcount--;
						$outhtml .= PHP_EOL;
						for($j = 0; $j < $tabcount; $j++){
							$outhtml .= '  ';
						}
					}else{
						$tabcount++;
						$outhtml .= PHP_EOL;
						for($j = 0; $j < $tabcount; $j++){
							$outhtml .= '  ';
						}
					}
				}
				$isexclude = false;
				$insideEnd = false;
			}
			if($html{$i} == '<'){
				if(strtoupper(substr($html,$i+1,3)) == 'PRE'){
					$insidepre = true;
					$tabcount--;
				}
			}
			if($html{$i} == '<'){
				if(strtoupper(substr($html,$i+1,4)) == '/PRE'){
					$insidepre = false;
					$tabcount--;
				}
			}
		}
		return $outhtml;
	}
	//Another port from javascript
	public static function autoindent($html){
		$regx = self::parse_params($html);
		$html = preg_replace('/<\!--.*:.*-->[\n\r]/','',$html);
		$html = preg_replace('/<\!--.*:.*-->/','',$html);
		$addtags = '';
		foreach($regx as $key => $param){
			$addtags .= '<!-- '.$key.':'.$param.' -->'.PHP_EOL;
		}
		return $addtags.self::prettify(self::uglify($html));
	}
	//Gets the given line of a file ...
	public static function get_line($line, $file){
		$file = file($file);
		return $file[$line];
	}
	
	//This function will create a footer to show the actions
	public static function create_footer(){
		if (self::$islogged == true){
			if (!isset($_REQUEST["action"])){//Don't show  the link to edit the page if we are on an action
				return '<span><a href="'.self::get_this_page(true).'action=changepass">Change password.</a></span> <span><a href="'.self::get_this_page(true).'action=editfile">Edit this page.</a></span> <span><a href="'.self::get_this_page(true).'action=filemanager">File manager</a></span> <span><a href="'.self::get_this_page(true).'action=delcache">Delete cache</a></span> <span><a href="'.self::get_this_page(true).'action=logout">Logout</a></span><hr>';
			}else{//If you are in a action you can not edit the current page, because you can not edit internal pages
				return '<span><a href="'.self::get_this_page(true).'action=changepass">Change password.</a></span> <span><a href="'.self::get_this_page(true).'action=filemanager">File manager</a></span> <span><a href="'.self::get_this_page(true).'action=delcache">Delete cache</a></span> <span><a href="'.self::get_this_page(true).'action=logout">Logout</a></span><hr>';
			}
		}else{
			return '<a href="'.self::get_this_page(true).'action=login">Login</a>';
		}
	}
	//To create an special page
	public static function load_action($action){
		switch($action){
			//Action that show the login form
			case "login":
				if(isset($_SESSION['return'])){ 
					unset($_SESSION['return']);
				}
				return Array(
					"content" => '<div id="login">
						<form action="'.self::get_this_page(false).'" method="POST">
							<input type="hidden" name="action" value="verifylogin"></input>
							<div><label for="password">Password: </label></div>
							<div><input type="password" name="password"></input></div>
							<div><input type="checkbox" id="keep" name="keep"></input><label for="keep">Keep logged</label></div>
							<div><input type="checkbox" id="idonurl" name="idonurl"></input><label for="idonurl">Login without cookies</label></div>
							<div><input type="submit" value="login"></input></div>
						</form>
					</div>',
					"params" => Array(
						"title" => "Login"
					)
				);
			break;
			//Action that verify if the password was correct, and logs the user in
			case "verifylogin":
				//echo password_hash($_POST['psw'], PASSWORD_BCRYPT).'<br>';
				$output = '';
				if (password_verify($_POST['password'] , self::get_line(1,PASSWORD_HASH_FILE))){//The password is stored on this file
					if (isset($_POST['idonurl'])){
						self::log_user_in_without_cookies();
					}else{
						self::log_user_in(isset($_POST['keep']));
					}
					if(is_file(TOKEN_FILE)){
						unlink(TOKEN_FILE);
					}
					$output = 'You are logged now, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}else{
					$output = 'You have typed the wrong password click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Login"
					)
				);
			break;
			//Action that logs the user out
			case "logout":
				if(self::$islogged == true){
					self::logout();
				}
				return Array(
					"content" => 'You are now logged out. click <a href="'.self::get_this_page(false).'">here</a> to go back.',
					"params" => Array(
						"title" => "Logout"
					)
				);
			break;
		}
		//Stop here if the user is not loged in ...
		if(self::$islogged != true){
			return Array(
				"content" => "You can not access this page without log in ...",
				"params" => Array(
					"title" => "Login required"
				)
			);
		}
		//Actions that don't generate a token
		switch($action){
			//This action erases the menu cache
			case "delcache":
				mfcms::delete_cache();
				$output = 'Cache deleted, click <a href="'.self::get_return_url().'">here</a> to go back.';
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Delete cache"
					)
				);
			break;
		}
		//Actions that requires login and generates a random token to avoid crosssite attack
		$token = self::generate_token();
		switch($action){
			//Action that shows the form to change the password
			case "changepass":
				$output = '<div id="login">
					<form action="'.self::get_this_page(false).'" method="POST">
						<input type="hidden" name="action" value="verifypchange"></input>
						<input type="hidden" name="token" value="'.$token.'"></input>
						<div><label>Old password: </label></div>
						<div><input type="password" name="oldpassword"></input></div>
						<div><label>New password: </label></div>
						<div><input type="password" name="newpassword"></input></div>
						<div><label>Verify new password: </label></div>
						<div><input type="password" name="newpassword2"></input></div>
						<div class="btn-spacer"></div>
						<div><input type="submit" value="Change password"></input></div>
					</form>
				</div>';
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Change password"
					)
				);
			break;
			//Action to show the current page or the specified page on a text area
			case "editfile":
				//If a file parameter was sent, that file will be edited, if not, the file on f parameter will be edited
				if(!isset($_GET['file'])){
					$to_edit = self::$file;
					$file_field = '';
				}else{
					$to_edit = pathjoin(CONTENT_DIR,$_GET['file']);
					//This hidden field will be used to indicate to the next step (saving) that we will not save the current page's file
					$file_field = '<input type="hidden" name="file" value="'.$_GET['file'].'"></input>';
				}
				self::sanitize($to_edit);
				if(file_exists($to_edit)){
					$output = '';
					$textToEdit = self::get_file_to_show($to_edit);
					//Show link to edit code only
					if(!(isset($_GET['nowysiwyg']) and ($_GET['nowysiwyg'] == 'true'))){//Show a link to code only if we have wysiwyg editor
						$query = Array();
						parse_str(parse_url($_SERVER['REQUEST_URI'])['query'],$query);
						unset($query['LOGINID']);
						unset($query['f']);//Because mfcms::get_this_page already gives the f
						$query['nowysiwyg']='true';
						$params = http_build_query($query);
						$output .= '<div id="code-only"><a href="'.mfcms::get_this_page(true).$params.'">Code only</a></div>';
					}else{ //this also enable the server side autoindent when wysiwyg editor is disabled
						$textToEdit = self::autoindent($textToEdit);
					}
					$output .= '
					<div id="wysiwyg-changer"></div>
					<div id="wysiwyg-buttons"></div>
					<div id="wysiwyg-wrapper"></div>
					<form id="file-editor" action="'.self::get_this_page(false).'" method="POST">
						<input type="hidden" name="token" value="'.$token.'"></input>
						<div class="st-table">
							<table border="1">
								<thead>
									<tr>
										<th colspan="4">Special tags</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>&lt;!-- title:page title --&gt;</td>
										<td>&lt;!-- menu:Menu/submenu --&gt;</td>
										<td>&lt;!-- position:10 --&gt;</td>
										<td>&lt;!-- nolink:1 --&gt;</td>
									</tr>
									<tr>
										<td>Page title</td>
										<td>Menu path</td>
										<td>Position on menu</td>
										<td>Not clickable</td>
									</tr>
								</tbody>
							</table>
						</div>
						<input type="hidden" name="action" value="savefile"></input>
						'.$file_field.'
						<div class="filecontent-wrapper">
							<textarea rows="30" cols="100" id="filecontent" name="filecontent">'.htmlentities($textToEdit).'</textarea>
						</div>
						<div class="btn-spacer"></div>
						<div class="filecontent-wrapper">
							<div class="filecontent-clone">
								<a class="button" onclick="document.getElementById(\'filecontent\').value = autoindent(document.getElementById(\'filecontent\').value)">AutoIndent</a>
								<a class="button" href="'.self::get_return_url().'">Discard</a>
								<input type="submit" value="     Save     ">
							</input></div>
						</div>
					</form>';
					if(!(isset($_GET['nowysiwyg']) and ($_GET['nowysiwyg'] == 'true'))){//Script for the wysiwyg editor
						$output .= '<script src="'.ROOT_URL.'js/editor.js"></script>';
					}
					$output .= '<script src="'.ROOT_URL.'js/indenter.js"></script>';
				}else{
					$output = 'You can not edit a file that don\'t exist, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}				
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Page editor"
					)
				);
			break;
			//This action is the file manager
			case "filemanager":
				if(isset($_GET['dir'])){//Sets the directory to show
					$current_dir_rel = $_GET['dir'];
					$current_dir_abs = pathjoin(CONTENT_DIR,$_GET['dir']);
				}else{
					$current_dir_rel = '/';
					$current_dir_abs = CONTENT_DIR;
				}
				self::sanitize($current_dir_abs);
				//Set this cookie so we will return here instead of the page
				$_SESSION['return'] = $current_dir_rel;
				$nextpage = self::get_this_page(true);//Link to this page
				//Starts the table
				$output = '<div style="overflow-x:auto;overflow-y:hidden;text-align:center;"><table class="file-list" border="1"><thead><tr><th colspan="4">Server files</th></tr></thead><tbody>';
				$directory = scandir($current_dir_abs);
				//For each file
				foreach($directory as $key => $value){
					if ($value == '.'){
					}else if ($value == '..'){
					}else{
						if(is_dir(pathjoin($current_dir_abs, $value))){//Print directory cell
							$output .= '<tr bgcolor="#f2e98e"><td><a href="'.$nextpage.'action=filemanager&dir='.urlencode(pathjoin($current_dir_rel, $value)).'" >['.$value.']</a></td>';
						}else if(is_file(pathjoin($current_dir_abs, $value))){//Print file cell
							if(preg_match('/jpg$|jpeg$|gif$|png$|webp$|bmp$|mov$|avi$|mkv$/',$value)){//This files should open in a new tab instead of being edited
								$output .= '<tr bgcolor="#b5d1ff"><td><a href="'.pathjoin(pathjoin(ROOT_URL.CONTENT_SUBDIR,$current_dir_rel), $value).'" target="_blank">'.$value.'</a></td>';
							}else{
								$output .= '<tr bgcolor="#b5d1ff"><td><a href="'.$nextpage.'action=editfile&file='.urlencode(pathjoin($current_dir_rel, $value)).'" >'.$value.'</a></td>';
							}
						}
						//Write move copy and delete cells
						$output .= '<td><a href="'.$nextpage.'action=movefile&file='.urlencode(pathjoin($current_dir_rel, $value)).'" >Move</a></td>';
						$output .= '<td><a href="'.$nextpage.'action=copyfile&file='.urlencode(pathjoin($current_dir_rel, $value)).'" >Copy</a></td>';
						$output .= '<td><a href="'.$nextpage.'action=deletefile&file='.urlencode(pathjoin($current_dir_rel, $value)).'" >Delete</a></td></tr>';
					}
				}
				if($current_dir_rel != '/'){//If we are not in root directory, print a link to the  parent
					$output .= '<tr><td colspan="4" bgcolor="#f2e98e"><a href="'.$nextpage.'action=filemanager&dir='.urlencode(goparent($current_dir_rel)).'" >Parent directory</a></td></tr>';
				}
				//Line to create a new file
				$output .= '<tr><form action="'.mfcms::get_this_page(false).'" method="POST">
					<input type="hidden" name="token" value="'.$token.'"></input>
					<input type="hidden" name="action" value="newfile"></input>
					<input type="hidden" name="parent" value="'.$current_dir_rel.'"></input>
					<td colspan="3" class="file-input"><input type="text" name="file" value=""></input></td>
					<td class="file-input"><input type="submit" value="New file"></input></td>
				</form></td></tr>';
				//Line to create a new directory
				$output .= '<tr><form action="'.mfcms::get_this_page(false).'" method="POST">
					<input type="hidden" name="token" value="'.$token.'"></input>
					<input type="hidden" name="action" value="newdir"></input>
					<input type="hidden" name="parent" value="'.$current_dir_rel.'"></input>
					<td colspan="3" class="file-input"><input type="text" name="file" value=""></input></td>
					<td class="file-input"><input type="submit" value="New dir"></input></td>
				</form></tr>';
				//Line to upload file
				$output .= '<tr><form enctype="multipart/form-data" action="'.mfcms::get_this_page(false).'" method="POST">
					<input type="hidden" name="token" value="'.$token.'"></input>
					<input type="hidden" name="action" value="receivefile"></input>
					<input type="hidden" name="parent" value="'.$current_dir_rel.'"></input>
					<td colspan="3" class="file-input"><input type="file" name="file" value=""></input></td>
					<td class="file-input"><input type="submit" value="Upload"></input></td>
				</form></tr>';
				$output .= '</tbody></table></div>';
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "File manager"
					)
				);
			break;
			//This shows the move or rename form
			case "movefile":
				if(isset($_GET['file'])){
					$output = '<div id="login"><form action='.self::get_this_page(false).' method="GET">
						<input type="hidden" name="token" value="'.$token.'"></input>
						<input type="hidden" name="action" value="domovefile"></input>
						<input type="hidden" name="oldfile" value="'.$_GET['file'].'"></input>
						<div>Destination file:</div>
						<div><input type="text" name="newfile" value="'.$_GET['file'].'"></input></div>
						<div class="btn-spacer"></div>
						<div><input type="submit" value="Move"></input></div>
					</form></div>';
				}else{
					$output = 'No file specified to move, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Move file"
					)
				);
			break;
			//This shows the form to copy a file
			case "copyfile":
				if(isset($_GET['file'])){
					$output = '<div id="login"><form action='.self::get_this_page(false).' method="GET">
						<input type="hidden" name="token" value="'.$token.'"></input>
						<input type="hidden" name="action" value="docopyfile"></input>
						<input type="hidden" name="oldfile" value="'.$_GET['file'].'"></input>
						<div>Destination file:</div>
						<div><input type="text" name="newfile" value="'.$_GET['file'].'"></input></div>
						<div class="btn-spacer"></div>
						<div><input type="submit" value="Copy"></input></div>
					</form></div>';
				}else{
					$output = 'No file specified to copy, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Copy file"
					)
				);
			break;
			//This shows the form to delete a file
			case "deletefile":
				if(isset($_GET['file'])){
					$output = '<div>Are you sure that you want to delete this file ?</div>
					<a href="'.self::get_this_page(true).'action=dodeletefile&file='.urlencode($_GET['file']).'&token='.$token.'">yes</a>
					<a href="'.self::get_return_url().'">NO, I DON\'T WANT TO DELETE THIS FILE !!!</a>';
				}else{
					$output = 'No file specified to delete, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "DELETE FILE"
					)
				);
			break;
		}
		//Actions that requires a valid token
		$tokenIsValid = false;
		if((isset($_GET['token'])) or (isset($_POST['token']))){
			$tokenIsValid = self::use_token($_REQUEST['token']);
		}
		if($tokenIsValid == false){
			return Array(
				"content" => "This token is not valid ...",
				"params" => Array(
					"title" => "Invalid Token"
				)
			);
		}
		switch($action){
			//Action that changes the password ...
			case "verifypchange":
				if (password_verify($_POST['oldpassword'] , self::get_line(1,PASSWORD_HASH_FILE))){//You must type the correct old password to change it
					if($_POST['newpassword'] == $_POST['newpassword2']){//You must type the same password to change it
						file_put_contents(PASSWORD_HASH_FILE, self::$exitheader.password_hash($_POST['newpassword'], PASSWORD_BCRYPT));
						$output = 'Password succefully changed, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}else{
						$output = 'You have typed 2 different passwords, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}
				}else{
					$output = 'Wrong old password, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Change password"
					)
				);
			break;
			//Action to save the edited file
			case "savefile":
				if(!isset($_POST['file'])){//If receive a file parameter, saves that file, if not, saves the current page
					$to_save = self::$file;
				}else{
					$to_save = pathjoin(CONTENT_DIR,$_POST['file']);
				}
				self::sanitize($to_save);
				if(!isset($_POST['filecontent'])){
					$output = 'the  browser have not sent content to save, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}else{
					if(file_put_contents($to_save, $_POST['filecontent'])){
						$output = 'The file has been saved, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}else{
						$output = 'Error saving the file, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}
					mfcms::delete_cache();
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Page editor"
					)
				);
			break;
			//This actions moves the specified file
			case "domovefile":
				if((isset($_GET['oldfile'])) && (isset($_GET['newfile']))){
					self::sanitize($_GET['oldfile']);
					self::sanitize($_GET['newfile']);
					$destination = pathjoin(CONTENT_DIR,$_GET['newfile']);
					if(preg_match('/.php$/',$_GET['newfile'])){//Prevent php file creation
						$output = 'You can not create php files, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}else if(file_exists($destination)){//Dont move file that exists ...
						$output = 'The destination file already exists, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}else{
						rename (pathjoin(CONTENT_DIR,$_GET['oldfile']),$destination);
						$output = 'The file has been moved, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}
					mfcms::delete_cache();
				}else{
					$output = 'No file specified to move, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Move file"
					)
				);
			break;
			//This action deletes the file
			case "dodeletefile":
				if((isset($_GET['file'])) and ($_GET['file'] != '')){
					$to_delete = pathjoin(CONTENT_DIR,$_GET['file']);
					self::sanitize($_GET['file']);
					if(is_file($to_delete)){//We use a different command to delete a file or a directory
						unlink($to_delete);
					}else if(is_dir($to_delete)){
						rrmdir($to_delete);
					}
					$output = 'The file has been deleted, click <a href="'.self::get_return_url().'">here</a> to go back.';
					mfcms::delete_cache();
				}else{
					$output = 'No file specified to delete, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "DELETE FILE"
					)
				);
			break;
			//This action copy a file 
			case "docopyfile":
				if((isset($_GET['oldfile'])) && (isset($_GET['newfile']))){
					self::sanitize($_GET['oldfile']);
					self::sanitize($_GET['newfile']);
					$to_copy = pathjoin(CONTENT_DIR,$_GET['oldfile']);
					$destination = pathjoin(CONTENT_DIR,$_GET['newfile']);
					if(preg_match('/.php$/',$_GET['newfile'])){//Prevent php file creation
						$output = 'You can not create php files, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}else if(file_exists($destination)){
						$output = 'The destination file already exists, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}else{
						if(is_file($to_copy)){//Different function to copy file and directory
							copy($to_copy,$destination);
						}else if(is_dir($to_copy)){
							recurse_copy($to_copy,$destination);
						}
						$output = 'The file has been copied, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}	
					mfcms::delete_cache();
				}else{
					$output = 'No file specified to copy, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "Copy file"
					)
				);
			break;
			//This action creates a new file
			case "newfile":
				if((isset($_POST['file'])) and ($_POST['file'] != '')){
					self::sanitize($_POST['file']);
					$destination = pathjoin(pathjoin(CONTENT_DIR,$_POST['parent']),$_POST['file']);
					if(preg_match('/.php$/',$_POST['file'])){
						$output = 'You can not create php files, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}else if(file_exists($destination)){
						$output = 'The destination file already exists, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}else{
						file_put_contents($destination,'');
						$output = 'The file has been created, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}
				}else{
					$output = 'No file specified file to create, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "New file"
					)
				);
			break;
			//This action creates a new directory
			case "newdir":
				if((isset($_POST['file'])) and ($_POST['file'] != '')){
					$destination = pathjoin(pathjoin(CONTENT_DIR,$_POST['parent']),$_POST['file']);
					if(file_exists($destination)){
						$output = 'The destination file already exists, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}else{
						self::sanitize($_POST['file']);
						mkdir($destination, 0777, true);
						$output = 'The directory has been created, click <a href="'.self::get_return_url().'">here</a> to go back.';
					}
				}else{
					$output = 'No file specified file to create, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "New directory"
					)
				);
			break;
			//This action receives an uploaded file
			case "receivefile":
				if(isset($_FILES['file'])){
					$destfile = $_FILES['file']['name'];
					if(preg_match('/.php$/',$destfile)){
						$destfile = $destfile.'.sane';
					}
					while(file_exists(pathjoin(pathjoin(CONTENT_DIR,$_POST['parent']),$destfile))){
						$farr = explode('.', $destfile);
						$farr[0] = $farr[0].'.2';
						$destfile = implode('.', $farr);
					}
					self::sanitize($destfile);
					self::sanitize($_POST['parent']);
					move_uploaded_file($_FILES['file']['tmp_name'],pathjoin(pathjoin(CONTENT_DIR,$_POST['parent']),$destfile));
					$output = 'File uploaded, click <a href="'.self::get_return_url().'">here</a> to go back.';
					mfcms::delete_cache();
				}else{
					$output = 'You have not sent a file, click <a href="'.self::get_return_url().'">here</a> to go back.';
				}
				return Array(
					"content" => $output,
					"params" => Array(
						"title" => "File uploader"
					)
				);
			break;
		}
	}
	
	//deletes the cache
	public static function delete_cache(){
		unlink(pathjoin(ROOT_DIR,'menu_cache.htmx'));
	}
	//This generate and return a new valid token
	public static function generate_token(){
		$token = md5(uniqid(rand(), true));
		if(file_exists(TOKEN_FILE)){
			file_put_contents(TOKEN_FILE,file_get_contents(TOKEN_FILE).PHP_EOL.$token);
		}else{
			file_put_contents(TOKEN_FILE,self::$exitheader.$token);
		}
		return $token;
	}
	//This uses a token, returns false if we use an invalid token
	public static function use_token($token){
		$tokens = file(TOKEN_FILE);
		$tokenIsValid = false;
		$toSave = trim(array_shift($tokens));//Remove the dead line
		foreach($tokens as $key => $value){
			if($token == trim($value)){
				$tokenIsValid = true;
			}else{
				$toSave .= PHP_EOL.trim($value);
			}
		}
		file_put_contents(TOKEN_FILE,$toSave);
		return $tokenIsValid;
	}
	//Gets the content of the page
	public static function get_page_content(){
		return self::$content;
	}
	//This function returns the given parameters if it exists or false if it does not exists
	public static function get_params($par){
		if (isset(self::$params[$par])){
			return self::$params[$par];
		}else{
			return false;
		}
	}
	//Gets the content of the menu
	public static function get_menu_content(){
		return self::$menu;
	}
	//Gets the content of the footer
	public static function get_footer(){
		return self::$footer;
	}
}
mfcms::init();

//This class is each li element
class mfcms_li{
	private $href;//The link
	private $html;//The content
	private $position;//The position
	public $hidden;//Is clickable ?
	public function sethref($p){
		$this->href = $p;
	}
	public function gethref(){
		return $this->href;
	}
	public function getposition(){
		return $this->position;
	}
	public function setposition($p){
		$this->position = $p;
	}
	public function sethtml($p){
		$this->html = $p;
	}
	public function gethtml(){
		return $this->html;
	}
	
	//Be a visible item
	public function printText(){
		if(($this->gethref() == '') or ($this->hidden == 1)){
			return '<li>'.$this->gethtml().'</li>';
		}else{
			return '<li><a href="'.$this->gethref().'">'.$this->gethtml().'</a></li>';
		}
	}
	public function __construct($p,$q,$r,$s){
		$this->sethref($p);
		$this->sethtml($q);
		$this->position = $r;
		$this->hidden = $s;
	}
}

//This class is a container to receive li itens or more ul itens
class mfcms_ul{
	private $lilist; //Item that will contain more li itens
	private $ullist; //Item that will contain more ul itens
	private $html;
	public function __construct($p){//$p is a string, because each ul inside a ul hava a li that must be before it, this variable will store the html content of that li.
		$this->lilist = Array();
		$this->ullist = Array();
		$this->html = $p; //html is the new $p
	}
	//This function adds the given element to the apropriate list
	public function addel($p){
		if (get_class($p) == 'mfcms_li'){
			$this->addli($p);
		}else if (get_class($p) == 'mfcms_ul'){
			$this->addul($p);
		}
	}
	//This function adds the given element to the li list, when an li element is sent, it has an html attribute, this html is the path on menu separated with slash, this function also searate the slash to send the item to a sub ul if needed
	private function addli($p){
		$subname = explode("/",$p->gethtml(),2);
		if(count($subname) > 1){//If the item go on a submenu
			$p->sethtml($subname[1]);//Remove the last item from html
			$this->addtoul($subname[0],$p);//Add the item to a sub ul
			$this->addli(new mfcms_li('',$subname[0],100,0));//Create a li to pair that ul
		}else{//If the item go here
			$mustcreatenew = true;
			foreach($this->lilist as $key => $value){
				if($value->gethtml() == $subname[0]){//Verify if this li already exists
					if($p->gethref() != '') {//Updates the li with info from the new li
						$this->lilist[$key] = $p;
						//$this->lilist[$key]->setposition($p->getposition());
						//$this->lilist[$key]->hidden = $p->hidden;
					}
					$mustcreatenew = false;
				}
			}
			if($mustcreatenew == true){//Add this li as a new li
				$this->lilist[] = $p;
			}
		}
	}
	private function addtoul($o,$p){//This function add an item to an specified sub ul
		$this->addel(new mfcms_ul($o));//Add the subul(if not exist)
		foreach($this->ullist as $key => $value){
			if($value->gethtml() == $o){//Add the item
				$value->addel($p);
			}
		}
	}
	private function addul($p){//Add the given ul element if it does not exist
		$mustcreatenew = true;
		foreach($this->ullist as $key => $value){
			if($value->gethtml() == $p->gethtml()){
				$mustcreatenew = false;
			}
		}
		if($mustcreatenew == true){
			$this->ullist[] = $p;
		}
	}
	
	//Getters and setters
	public function sethtml($p){
		$this->html = $p;
	}
	public function gethtml(){
		return $this->html;
	}
	
	//This function sort the element using the propriety from getposition
	public function printText(){
		$rtrn = '<ul>';
		foreach ($this->lilist as $key => $value) {
			$order[$key] = $value->getposition();
		}
		array_multisort($order, SORT_ASC, $this->lilist);
		
		foreach($this->lilist as $key => $value){
			$rtrn .= $value->printText();
			foreach($this->ullist as $key2 => $value2){
				if($value2->gethtml() == $value->gethtml()){
					$rtrn .= $value2->printText();
				}
			}
		}
		$rtrn .= '</ul>';
		return $rtrn;
	}
}

//Function that copy a directory recursively
function recurse_copy($src,$dst) { 
    $dir = opendir($src); 
    @mkdir($dst); 
    while(false !== ( $file = readdir($dir)) ) { 
        if (( $file != '.' ) && ( $file != '..' )) { 
            if ( is_dir($src . '/' . $file) ) { 
                recurse_copy($src . '/' . $file,$dst . '/' . $file); 
            } 
            else { 
                copy($src . '/' . $file,$dst . '/' . $file); 
            } 
        } 
    } 
    closedir($dir); 
} 

//Function that delets a file recursively
 function rrmdir($dir) { 
   if (is_dir($dir)) { 
     $objects = scandir($dir); 
     foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (is_dir($dir."/".$object))
           rrmdir($dir."/".$object);
         else
           unlink($dir."/".$object); 
       } 
     }
     rmdir($dir); 
   } 
 }
//This function join 2 paths
function pathjoin($p1, $p2){
	while(substr($p1,-1,1)=='/'){
		$p1 = substr($p1,0,-1);
	}
	while(substr($p2,0,1) == '/'){
		$p2 = substr($p2, 1, strlen($p2));
	}
	return $p1.'/'.$p2;
}

//This function prints the parent directory
function goparent($p1){
	$sl = '';
	$el = '';
	if(substr($p1,0,1) == '/') $sl = '/';
	if(substr($p1,-1,1)=='/') $el = '/';
	while(substr($p1,-1,1)=='/'){
		$p1 = substr($p1,0,-1);
	}
	while(substr($p1,0,1) == '/'){
		$p1 = substr($p1, 1, strlen($p1));
	}
	$p1a = explode('/',$p1);
	array_pop($p1a);
	$p1 = implode('/', $p1a);
	return $sl.$p1.$el;
}

class mfcms_menucreator{
	public static $menuItens;//This variable will be an associative array
	//Each item will have this indexes:
	//url: the url for that file
	//text: The text that will appear on menu
	//position: the position of the item on menu
	//nolink: a string with 0 or 1 that indicate if the menu item should be clickable
	
	//Given a directory relative to CONTENT_DIR, this function add itens to $menuItens array
	public static function createMenu($dir){
		$completeDir = CONTENT_DIR . $dir;
		$thisdir = scandir($completeDir);
		foreach($thisdir as $key => $value){
			$completeFile = $completeDir . "/" . $value;
			$relativeFile = $dir . "/" . $value;
			if (!in_array($value,array(".",".."))){//Ignore this directories
				if(is_dir($completeFile)){
					self::createMenu($relativeFile);
				}else{
					if(preg_match('/.htmx$/',$value) == 1){//Verify if the file is an .htmx file
						$pageParams = mfcms::parse_params(file_get_contents($completeDir . "/" . $value));
						if (isset($pageParams["menu"])){//For each file that should appear on menu
							if(!isset($pageParams["position"])) $pageParams["position"] = '100';
							if(!isset($pageParams["nolink"])) $pageParams["nolink"] = '0';
							self::$menuItens[] = Array('url' => substr($relativeFile,1,strlen($relativeFile) - 6), 'text' => $pageParams["menu"], 'position' => intval($pageParams["position"]), 'nolink' => intval($pageParams["nolink"]));
						}
					}
				}
			}
		}
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">
<html>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php echo mfcms::get_params('title');?></title>
		<link rel="stylesheet" type="text/css" href="<?php echo ROOT_URL.'style/style.css'?>">
		<?php if(mfcms::$islogged == true): ?>
			<link rel="stylesheet" type="text/css" href="<?php echo ROOT_URL.'style/admin.css'?>">
		<?php endif;?>
	</head>
	<body>
		<div class="main-content">
			<div style="vertical-align:top" id="menu-div" class="menu-div not-hidden">
				<h3 onclick="toggleVisibility();"><span class="menu-plus">(+)</span><span class="menu-minus">(-)</span> Navigation</h3>
				<div class="menu-body">
					<a href="#content-div" style="display:none;">Jump</a>
					<?php echo mfcms::get_menu_content(); ?>
				</div>
				<hr>
			</div>
			<div style="vertical-align:top" class="content-div" id="content-div">
				<h1><?php echo mfcms::get_params('title');?></h1>
				<hr>
				<div class="content-body">
					<?php echo mfcms::get_page_content(); ?>
				</div>
			</div>
		</div>
		<div class="footer-wrapper">
			<div class="footer-spacer"></div>
			<div class="footer">
				<hr>
				<?php echo mfcms::get_footer();?>
				<div class="credit-footer">Powered by Mother***king CMS</div>
			</div>
		</div>
		<script>
			<!--
			function toggleVisibility(){
				var el = document.getElementById('menu-div');
				if(/not-hidden/.test(el.className)){
					el.className = el.className.replace('not-hidden','hidden');
				}else if(/hidden/.test(el.className)){
					el.className = el.className.replace('hidden','not-hidden');
				}
			}
			toggleVisibility();
			-->
		</script>
	</body>
</html>
