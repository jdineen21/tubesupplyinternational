<?php
// Router for PHP's built-in web server that copies the rewrite rules in
// web.config, so the sites can be run locally:
//   php -S 127.0.0.1:8000 -t sites/<site> tests/router.php
// Must run on PHP 5.6 (original code) as well as 8.5, so no new syntax.
// Variables are prefixed __r so they cannot clash with the site's globals.

$__rRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$__rPath = ltrim(urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
$__rRewritten = false;

// Rule "(.*)\.htm" -> {R:1}.php, except under admin/components
if (!preg_match('#^admin/components/#', $__rPath) && preg_match('#^(.*)\.htm$#', $__rPath, $__rMatch)) {
	$__rPath = $__rMatch[1] . '.php';
	$__rRewritten = true;
}

$__rFile = $__rRoot . '/' . $__rPath;
if (is_dir($__rFile) && is_file(rtrim($__rFile, '/') . '/index.php')) {
	$__rFile = rtrim($__rFile, '/') . '/index.php';
	$__rRewritten = true;
}

if (preg_match('#^(admin|common|frontend|atlas)#', $__rPath) || is_file($__rFile)) {
	if (!is_file($__rFile)) {
		header('HTTP/1.0 404 Not Found');
		echo 'Not found';
		return true;
	}
	if (substr($__rFile, -4) !== '.php') {
		if (!$__rRewritten) {
			return false; // let the built-in server send the static file
		}
		readfile($__rFile);
		return true;
	}
	$_SERVER['SCRIPT_FILENAME'] = $__rFile;
	$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = substr($__rFile, strlen($__rRoot));
	chdir(dirname($__rFile));
	unset($__rRoot, $__rPath, $__rRewritten, $__rMatch);
	include $__rFile;
	return true;
}

// Rule "Imported Rule 1-1": anything that is not a file goes to index.php?url=...
$_GET['url'] = '/' . $__rPath;
$_SERVER['SCRIPT_FILENAME'] = $__rRoot . '/index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
chdir($__rRoot);
unset($__rPath, $__rRewritten, $__rFile, $__rMatch);
include $__rRoot . '/index.php';
return true;
