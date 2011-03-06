<?php
require_once('PHPQuery.php');
$q = isset($_GET['q']) ? $_GET['q'] : null;
$format = isset($_GET['format']) ? $_GET['format'] : "json";
$callback = isset($_GET['callback']) ? $_GET['callback'] : null;
try {
    $phpQuery = new PHPQuery(array(
                "q" => "SELECT class AS ax,href,content FROM html WHERE url='http://estebanfuentealba.wordpress.com' AND (xpath='//a[@class=\"title\"]' OR xpath='//title')",
                "format" => $format,
                "callback" => $callback
            ));
    echo $phpQuery->query();
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
