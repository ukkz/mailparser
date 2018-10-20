<?php

namespace MailParserTest;

use PHPUnit\Framework\TestCase;
use MailParser\MailUtil;

class MailUtilTest extends TestCase
{
    public $sample_mail_gmail;
    public $sample_mail_docomo;

    public function setUp()
    {
        $sample_mail_gmail = file_get_contents(__DIR__.'/sample_gmail.txt');
        $sample_mail_docomo = file_get_contents(__DIR__.'/sample_docomo.txt');
        $this->sample_mail_gmail = MailUtil::bisect($sample_mail_gmail);
        $this->sample_mail_docomo = MailUtil::bisect($sample_mail_docomo);
    }

    public function test_reading_address_and_name()
    {
        # 1
        $header = MailUtil::header_parse($this->sample_mail_gmail['head']);
        $address_from_raw = MailUtil::getHeader('from', $header);
        $address_from = MailUtil::address_parse($address_from_raw, $address_name);

        $this->assertEquals('sender@fromgmail.test', $address_from);
        $this->assertEquals('Sender', $address_name);

        # 2
        $header = MailUtil::header_parse($this->sample_mail_docomo['head']);
        $address_from_raw = MailUtil::getHeader('from', $header);
        $address_from = MailUtil::address_parse($address_from_raw);

        $this->assertEquals('sender@fromdocomo.test', $address_from);
    }

    public function test_reading_mail_subject()
    {
        # 1
        $header = MailUtil::header_parse($this->sample_mail_gmail['head']);
        $subject_raw = MailUtil::getHeader('subject', $header);
        $subject_decoded = MailUtil::header_decode($subject_raw);

        $this->assertEquals('おにぎり', $subject_decoded);

        # 2
        $header = MailUtil::header_parse($this->sample_mail_docomo['head']);
        $subject_raw = MailUtil::getHeader('subject', $header);
        $subject_decoded = MailUtil::header_decode($subject_raw);

        $this->assertEquals('ステーキ', $subject_decoded);
    }
}
