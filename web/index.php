<?php

require '../vendor/autoload.php';
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/views',
));

// $app->after(function (Request $request, Response $response) {
//   $response->headers->set('Access-Control-Allow-Origin', '*');
// });

// Our web handlers

$app->get('/', function () use ($app) {
    $app['monolog']->addDebug('logging output.');
    return $app['twig']->render('index.twig');
});

$app->get('/lcount', function () use ($app) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    $findCityUrl = 'https://kabinet.pecom.ru/api/v1/BRANCHES/FINDBYTITLE/';
    $ch = curl_init();
    $cityOpts = array(
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_URL => $findCityUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO => dirname(__FILE__) . '/cacert-kabinet_pecom_ru.pem',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_USERPWD => 'dixiesasha:35639F849D96D1C9E350518F76E1982EC41C9FE9',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json; charset=utf-8',
        ));
    curl_setopt_array($ch, $cityOpts);
    curl_setopt_array($ch, array(
        CURLOPT_POSTFIELDS => json_encode(array('title' => $_GET['cityTo'])),
    ));

    $cities = curl_exec($ch);
    $parsedCities = json_decode($cities);
    // return var_dump($parsedCities);
    $cityTo = $parsedCities->items[0]->branchId;
    curl_close($ch);
    $chh = curl_init();
    curl_setopt_array($chh, $cityOpts);
    curl_setopt_array($chh, array(
        CURLOPT_POSTFIELDS => json_encode(array('title' => $_GET['cityFrom'])),
    ));
    $citiesFromResp = curl_exec($chh);
    $parsedCitiesFrom = json_decode($citiesFromResp);
    // return var_dump($parsedCities);
    $cityFrom = $parsedCitiesFrom->items[0]->branchId;
    curl_close($chh);

    $chu = curl_init();

    // Настройки HTTP-клиента
    curl_setopt_array($chu, array(
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO => dirname(__FILE__) . '/cacert-kabinet_pecom_ru.pem',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_USERPWD => 'dixiesasha:35639F849D96D1C9E350518F76E1982EC41C9FE9',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json; charset=utf-8',
        ),
    ));
    // Данные для запроса
    // weight, volume, size, places, cityTo, cityFrom, insPrice
    // return  $_GET['volume'] / $_GET['places'];

    $allCargos = array_fill(0, $_GET['places'], array(
        'volume' => $_GET['volume'] / $_GET['places'],
        'weight' => $_GET['weight'] / $_GET['places'],
        'maxSize' => $_GET['size'] / $_GET['places'],
        'sealingPositionsCount' => 1,
        'isHP' => true
    ));
    $request_data = array(
        'senderCityId' => $cityFrom,
        'receiverCityId' => $cityTo,
        "isInsurance" => true,
        "isPickUp" => true,
        "isDelivery" => true,
        'isInsurancePrice' => $_GET["insPrice"],
        'calcDate' => date("yy-m-d"),
        'Cargos' => $allCargos,
    );

// Параметры конкретного запроса к API
    curl_setopt_array($chu, array(
        CURLOPT_URL => 'https://kabinet.pecom.ru/api/v1/calculator/calculateprice/',
        CURLOPT_POSTFIELDS => json_encode($request_data),
    ));

// Выполнение запроса
    $returndata = curl_exec($chu);
    $status_code = @curl_getinfo($chu, CURLINFO_HTTP_CODE);
    $decodedReturn = json_decode($returndata);
    return ($app->json($decodedReturn));
    $costTotal = $decodedReturn->transfers[0]->costTotal;

    return new Response($costTotal, 200);
});

$app->run();
