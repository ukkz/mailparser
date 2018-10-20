<?php

namespace MailParserTest;

use PHPUnit\Framework\TestCase;
use MailParser\MailParser;

class MailParserTest extends TestCase
{
    public $sample_mail_gmail;
    public $sample_mail_docomo;

    public function setUp()
    {
        $this->sample_mail_gmail = new MailParser(file_get_contents(__DIR__.'/sample_gmail.txt'));
        $this->sample_mail_docomo = new MailParser(file_get_contents(__DIR__.'/sample_docomo.txt'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_show_dump_text()
    {
        // dump output
        echo "\n##### Sample 1 #####\n";
        echo $this->sample_mail_gmail->dump();
        echo "##### Sample 2 #####\n";
        echo $this->sample_mail_docomo->dump();
        echo "\n";
    }

    public function test_get_subject()
    {
        # 1
        $this->assertEquals('おにぎり', $this->sample_mail_gmail->subject());

        # 2
        $this->assertEquals('ステーキ', $this->sample_mail_docomo->subject());
    }

    public function test_get_sender()
    {
        # 1
        $this->assertEquals('sender@fromgmail.test', $this->sample_mail_gmail->addressFrom());

        # 2
        $this->assertEquals('sender@fromdocomo.test', $this->sample_mail_docomo->addressFrom());
    }

    public function test_read_body_text()
    {
        # 1
        $this->assertContains('めんたいこ', $this->sample_mail_gmail->getBody()->readText());

        # 2
        $this->assertContains('ミディアムレア', $this->sample_mail_docomo->getBody()->readText());
    }
}
