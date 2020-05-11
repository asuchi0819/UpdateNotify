<?php
$siteUrl = '';  // スクレイピングするURLを入力
$token = ""; // LINE Notifyのトークンを入力

require_once './vendor/autoload.php';
use Goutte\Client;
$client = new Client();
$crawler = $client->request('GET', $siteUrl);

$datas = [];
$i = 0;

//次のURLのコードを一部改変して利用 https://qiita.com/iitenkida7/items/576a8226ba6584864d95
//LINE Notifyで送信するコード
function post_message($message){
  global $token;
  $data = http_build_query( [ 'message' => $message ], '', '&');

  $options = [
    'http' => [
      'method' => 'POST',
      'header' => "Authorization: Bearer ".$token."\nContent-Type: application/x-www-form-urlencoded\nContent-Length: ".strlen($data)."\n",
      'content' => $data,
    ]
  ];
  $context = stream_context_create($options);
  $resultJson = file_get_contents('https://notify-api.line.me/api/notify', false, $context);
  $resultArray = json_decode($resultJson, true);
  if($resultArray['status'] !== 200)  {
    return false;
  }
  return true;
}

// Goutteを使って、タイトルとURLを取得。また、過去に送ったかどうかの識別用にkeyも生成。
$crawler->filter('#diary .diary')->each(function($element){
  global $datas, $i;
  $datas[] = [
    'key' => $element->filter('h3')->text().$element->filter('.info li')->eq(0)->text(),
    'title' => $element->filter('h3')->text(),
    'url' => $element->filter('.info li a')->eq(1)->attr('href')
  ];
  $i++;
});

// datas.jsonを取得し配列の形にデコード。
$json = file_get_contents('./datas.json');
$jsonArray = json_decode($json, true);

// 過去のスクレイピング時にJSONに記録されているか確認。もし、記録されていなかったらLINE Notifyを使って送信。また、JSONに記録。
foreach($datas as $data){
  if(!in_array($data['key'], $jsonArray)){
    $return = post_message("\r\nホームページが更新されました\r\n\r\n".$data['title']."\r\n\r\n".$data['url']);
    if ($return) {
      $jsonArray[] = $data['key'];
    }
  }
}

// 過去の記録と、今回送信した記録をJSON形式でエンコードし保存。
$openJson = fopen('./datas.json', 'w+b');
fwrite($openJson, json_encode($jsonArray, JSON_UNESCAPED_UNICODE));
fclose($openJson);
