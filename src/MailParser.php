<?php

namespace MailParser;

class MailParser
{
    private $headers = [];
    private $body = [];

    /**
     * 引数にメールフォーマット文字列全体をそのままいれる。
     * $this->getBody()->readText() でメールの文字列が取得できる。
     *
     * @param string $entire_message
     */
    public function __construct(string $entire_message)
    {
        // ヘッダとボディ分割
        $separate = MailUtil::bisect($entire_message);
        $this->headers = MailUtil::header_parse($separate['head']);
        $this->body = new MailBody($separate['body'], MailUtil::getHeader('content-type', $this->headers));
        $this->body->setContentTransferEncoding(MailUtil::getHeader('content-transfer-encoding', $this->headers));
    }

    public function getBody(): MailBody
    {
        return $this->body;
    }

    /**
     * ダンプテキストを返す。
     *
     * @return string
     */
    public function dump(): string
    {
        $text =
        'Date        : '.$this->date()->format(\DateTime::RFC2822)."\n".
        'Subject     : '.$this->subject()."\n".
        'NameFrom    : '.$this->nameFrom()."\n".
        'AddressFrom : '.$this->addressFrom()."\n".
        'NameTo      : '.$this->nameTo()."\n".
        'AddressTo   : '.$this->addressTo()."\n".
        'Structure   : '."\n".json_encode($this->getBody()->getStructure(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n".
        'Body (Text) : '."\n".$this->getBody()->readText()."\n";
        
        return $text;
    }

    public function date(): \DateTime
    {
        // RFC5322 - 3.3
        return new \DateTime(MailUtil::getHeader('date', $this->headers));
    }

    public function subject(): string
    {
        $subject_raw = MailUtil::getHeader('subject', $this->headers);
        return MailUtil::header_decode($subject_raw);
    }

    public function nameFrom(): string
    {
        MailUtil::address_parse(MailUtil::getHeader('from', $this->headers), $name);
        return MailUtil::header_decode($name);
    }

    public function addressFrom(): string
    {
        return MailUtil::address_parse(MailUtil::getHeader('from', $this->headers));
    }

    public function nameTo(): string
    {
        MailUtil::address_parse(MailUtil::getHeader('to', $this->headers), $name);
        return MailUtil::header_decode($name);
    }

    public function addressTo(): string
    {
        return MailUtil::address_parse(MailUtil::getHeader('to', $this->headers));
    }

    public static function subject_encode($subject): string
    {
        return '=?UTF-8?B?'.base64_encode($subject).'?=';
    }
}
