<?php
/*
To install the Mother***king CMS, you just need to extract it on the server
directory and edit the config.php, an apropriate .htaccess file will be
generated, you must delete .htaccess and menu_cache.htmx if you change the
settings or change the installation directory.
*/
define('CONTENT_SUBDIR','content/'); //Your content goes here
define('ROOT_URL', '/'); //The directory where you extracted the Mother***king CMS, relative to the server root, starts and ends with slash
define('LOGIN_ID_FILE', 'loginId.php'); //The file that will store the login id
define('LOGIN_ID_FILE_NC', 'loginIdnc.php'); //The file that will store the login id without cookie
define('PASSWORD_HASH_FILE', 'password_hash.php'); //The file that will store the password hash
define('TOKEN_FILE', 'token.php'); //The file that will store the token list
define('USE_HTACCESS', true); //Will the installation use .htaccess file ?