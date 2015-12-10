<?php
header('Content-Type: application/json');
define('WEBSITE', 'http://www.studentenwerk-oldenburg.de/de');
require_once ('simple_html_dom.php');
require_once ('functions.php');

$uri = (isset($_GET['uri']) ? $_GET['uri'] : NULL); // ?uri=/gastronomie/speiseplaene/uhlhornsweg-ausgabe-b.html

if (empty($uri)) {
    $mensen = getMensaUris('/gastronomie/speiseplaene.html');
} else {
    $mensen = array($uri);
    unset($uri);
}
$output = array();
foreach ($mensen as $mensaName => $mensaUri) {
	//var_dump(getLunchObj($mensa));
	$output[sanitize_title_with_dashes($mensaName)] = json_decode(getJson($mensaUri, '120 minutes'));
}
echo json_encode($output);