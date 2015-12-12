<?php
header('Content-Type: application/json');
define('WEBSITE', 'http://www.studentenwerk-oldenburg.de/de');
define('OVERVIEW_URI', '/gastronomie/speiseplaene.html');
define('DETAILVIEW_URI', '/gastronomie/speiseplaene/%s.html');
define('OVERVIEW', (isset($_GET['mensa']) ? FALSE : TRUE));
define('SCRIPT_URL', "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
require_once ('simple_html_dom.php');
require_once ('functions.php');


$output = '';
if (OVERVIEW) {
	$url = WEBSITE.OVERVIEW_URI;
	$output = getJson($url, '7 days');
} else {
	$url = WEBSITE. sprintf(DETAILVIEW_URI, $_GET['mensa']);
	$output = getJson($url, '120 minutes');
}
echo $output;