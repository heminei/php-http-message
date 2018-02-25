<?php

require_once '../vendor/autoload.php';


//var_dump(getallheaders());
//var_dump($_SERVER);

$response = new HemiFrame\Lib\Http\Message\Response();
$response->setImmutable(false);
$serverRequest = new \HemiFrame\Lib\Http\Message\ServerRequest();
$serverRequest->setImmutable(false);
$serverRequest->fromGlobals();

var_dump($serverRequest);


$response->withHeader("Api-Key", "101010");
$response->withAddedHeader("Api-Key", "202020");

//var_dump($response->hasHeader("Api-Key"));
//var_dump($response->getHeader("Api-Key"));
//var_dump($response->getHeaderLine("Api-Key"));


header("HTTP/" . $response->getProtocolVersion() . " " . $response->getStatusCode() . " " . $response->getReasonPhrase(), true, $response->getStatusCode());
echo $response->getBody();
