<?php

namespace MailParser;

class MailBody
{
    public $is_multipart = false;
    private $body = '';
    private $parts = []; // マルチパートのとき同じオブジェクトがはいる
    private $charset = 'UTF-8';
    private $mime = 'application/octet-stream';
    private $content_transfer_encoding = '7bit';

    /**
     * メールのボディ部のクラス、マルチパートならパートごとに入れ子になる。
     * 通常は直接newせずにMailParserクラスから使う。
     *
     * @param string $body_raw
     * @param string $content_type_with_parameter
     */
    public function __construct(string $body_raw, string $content_type_with_parameter)
    {
        // ボディ文字列
        $this->body = trim($body_raw);

        // コンテンツタイプ
        $s = explode(';', $content_type_with_parameter, 2);
        $this->mime = strtolower($s[0]);
        $parameter = (!empty($s[1])) ? trim($s[1]) : '';

        if (strpos($content_type_with_parameter, 'multipart/') !== false) {
            // マルチパートのとき
            $this->is_multipart = true;
            $multi_bodies = MailUtil::multipart_explode($content_type_with_parameter, $this->body);
            foreach ($multi_bodies as $body) {
                // ボディごとに子インスタンスを作る
                $message = MailUtil::bisect($body);
                $headers = MailUtil::header_parse($message['head']);
                $newBodyInstance = new self($message['body'], MailUtil::getHeader('content-type', $headers));
                $newBodyInstance->setContentTransferEncoding(MailUtil::getHeader('content-transfer-encoding', $headers));
                // 直下のボディパートのみ$this->partsに格納する
                $this->parts[] = $newBodyInstance;
            }
        } elseif (preg_match("/charset=\"(.*)\"/i", $parameter, $matches)) {
            // プレーンテキストなど
            $this->charset = $matches[1];
        }
    }

    /**
     * Content-Typeを返す。
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->mime;
    }

    /**
     * 7bit/8bit/binary/quoted-printable/base64 のいずれか。
     * デフォルトは7bit（テキスト）だが、
     * quoted-printableかbase64以外であればそのまま出力されるので特に使わなくてもよい。
     *
     * @param string $encoding
     * @return void
     */
    public function setContentTransferEncoding(string $encoding = '7bit')
    {
        $this->content_transfer_encoding = trim(strtolower($encoding));
    }

    /**
     * ボディ本体を返す。
     * エンコード処理などはここで行われる。
     * このインスタンスがマルチパートの場合は空文字列を返す。
     *
     * @return string
     */
    public function read(): string
    {
        // 最初に符号化を解除しておく
        if ($this->content_transfer_encoding === 'base64') {
            $result = base64_decode($this->body);
        } elseif ($this->content_transfer_encoding === 'quoted-printable') {
            $result = quoted_printable_decode($this->body);
        } else {
            $result = $this->body;
        }

        // MIMEごとに出力切替
        switch ($this->mime) {
            // テキスト類
            case 'text/plain':
            case 'text/html':
            case 'text/csv':
            case 'application/xhtml+xml':
            case 'application/json':
                return mb_convert_encoding($result, 'UTF-8', $this->charset);
                break;
            // その他
            default:
                return $result;
                break;
        }
    }

    /**
     * このインスタンスがマルチパートの場合に子パート（1階層下）のイテレータを返す。
     * マルチパートでない場合は何も返さない。
     *
     * @return iterable
     */
    public function getParts(): iterable
    {
        foreach ($this->parts as $p) {
            yield $p;
        }
    }

    /**
     * メッセージ構造を取得。
     * 返却値は配列なのでjson_encodeするとデバッグに便利。
     *
     * @return array
     */
    public function getStructure(): array
    {
        // マルチパートのときは、MIME名が「キー」
        // それ以外のときは、MIME名が「値」となる
        if ($this->is_multipart) {
            $structure = [];
        } else {
            $structure[] = $this->getContentType();
        }

        // 再帰
        foreach ($this->getParts() as $p) {
            $structure[$this->getContentType()][] = $p->getStructure();
        }

        return $structure;
    }
    
    /**
     * このインスタンスおよび子インスタンス（パート）からtext/plainであるパートを探して
     * 見つかったらその文字列を返す。
     * text/htmlのみ見つかった場合はtextに変換する。
     * テキスト化できるものがなければ空文字列を返す。
     *
     * @return string
     */
    public function readText(): string
    {
        $text = $this->readSpecificContentOne('text/plain');
        $html = $this->readSpecificContentOne('text/html');
        if (empty($text)) {
            if (empty($html)) {
                // textもhtmlもなければ空文字列
                return '';
            } else {
                // htmlのみある場合はテキストに変換
                $nojs = preg_replace("/<script.*?>.*?<\/script.*?>/is", '', $html); // JS除去
                $nocss = preg_replace("/<style.*?>.*?<\/style.*?>/is", '', $nojs); // CSS除去
                return trim(strip_tags($nocss)); // HTML除去 & 空白などの削除
            }
        } else {
            // textがある場合はそのまま返す
            return trim($text);
        }
    }

    /**
     * text/htmlを返す。
     *
     * @return string
     */
    public function readHtml(): string
    {
        return $this->readSpecificContentOne('text/html');
    }

    /**
     * 指定されたContent-Typeのbodyパートをマルチパート内から探し見つかったら1つだけ返す。
     * 最初に見つかったパートのみとなる。
     *
     * @param string $content_type
     * @return string
     */
    public function readSpecificContentOne(string $content_type): string
    {
        $body = '';
        if ($this->getContentType() === $content_type) {
            // このインスタンスが指定のコンテンツタイプならbodyを返す
            $body = $this->read();
        } else {
            // そうでなければ子パートから再帰的に検索
            foreach ($this->getParts() as $p) {
                $part_body = $p->readSpecificContentOne($content_type);
                if (!empty($part_body)) {
                    $body = $part_body;
                    break;
                }
            }
        }

        return $body;
    }

    /**
     * マルチパート内の特定のContent-Typeを探してすべて配列で返す。
     * 配列は1次元で中身はすべてMailBodyオブジェクト。
     *
     * @param string $content_type
     * @return array
     */
    public function readSpecificContents(string $content_type): array
    {
        $bodies = [];
        if ($this->getContentType() === $content_type) {
            // このインスタンスが指定のコンテンツタイプならbodyを返す
            $bodies[] = self;
        } else {
            // そうでなければ子パートから再帰的に検索
            foreach ($this->getParts() as $p) {
                $part_body = $p->readSpecificContents($content_type);
                if (!empty($part_body)) {
                    $bodies[] = $part_body;
                }
            }
        }

        return $bodies;
    }

    /**
     * 文字列化した場合は、マルチパートの場合は構造、それ以外はbodyをそのまま返す。
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->is_multipart) {
            return 'Multipart: '.json_encode($this->getStructure(), JSON_UNESCAPED_SLASHES);
        } else {
            return $this->read();
        }
    }
}
