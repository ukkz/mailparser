# Simple mail parser for PHP

This is an independent PHP library for parsing internet message format ([RFC5322](https://tools.ietf.org/html/rfc5322)).  

[![Build Status](https://travis-ci.org/ukkz/mailparser.svg?branch=master)](https://travis-ci.org/ukkz/mailparser)

## About

PHPでインターネットメッセージフォーマット（[RFC5322](https://tools.ietf.org/html/rfc5322)）をパースするライブラリです。    
有名どころのライブラリあるけどなんかよくわからんが全然インストールできなかったので再発明したものの供養です。よって依存関係はありません。  
RFCは流し読みなので全部はテストしてません。

## Requirements

- PHP 7 以上（タイプヒントがあるのでPHP5だとたぶんエラー出る）

## Install

`composer require ukkz/mailparser`

## Usage

- /etc/aliases に追記

`test_local_user: "| /usr/bin/php -f /tmp/example.php > /tmp/mailparser.log 2>&1"`

- /tmp/example.php

```
use MailParser\Mailparser;

// 標準入力より
$entire_message = file_get_contents('php://stdin');

// このクラス
$mailparser = new MailParser($entire_message);

$sender_address = $mailparser->addressFrom();
$target_address = $mailparser->addressTo();
$mail_title = $mailparser->subject();
$text_body = $mailparser->getBody()->readText();

// 好きな形式で出力など
echo "From: $sender_address \n$text_body";
```

`test_local_user@yourhost`宛てに適当にメールを送ります。  
うまくいっていれば`/tmp/mailparser/log`にそれっぽいのが出力されているはずです。



## License

MITライセンスです。LICENSE.txtを参照のこと。