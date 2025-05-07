<?php
require __DIR__ . '/../vendor/autoload.php';

use DiDom\Document;

$client = new \GuzzleHttp\Client();
$response = $client->get('https://www.agroprombank.com/');
$html = $response->getBody()->getContents();
$document = new Document();
$document->loadHTML($html);

$currencyItems = $document->find('#rate-ib tbody tr td:nth-child(2)');
$currencyBuy = $document->find('.exchange-rates-item tbody tr td:nth-child(3)');
$currencySel = $document->find('.exchange-rates-item tbody tr td:nth-child(4)');
$result = [];
foreach ($currencyItems as $key => $item) {
    $dataValue1 = $item->getAttribute('data-name1') ?: 'N/A';
    $dataValue2 = $item->getAttribute('data-name2') ?: 'N/A';
    $dataBuy = isset($currencyBuy[$key]) ? $currencyBuy[$key]->text() : 'N/A';
    $dataSel = isset($currencySel[$key]) ? $currencySel[$key]->text() : 'N/A';
    $result[] = [
        $dataValue1 => $dataValue1,
        $dataValue2 => $dataValue2,
        'buy' => $dataBuy,
        'sell' => $dataSel,
    ];
}
$s = (new \App\Telegram\Currency())->getDataFromBank();



