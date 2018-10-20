<?php

namespace MailParser;

class MailUtil
{
    const LINE_END = "\n";
    const PREG_EMAIL_ADDRESS_PATTERN = "[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*";

    /**
     * メッセージをヘッダとボディに分割する。
     * ヘッダはheadキー、ボディはbodyキー。
     *
     * @param string $message
     * @return array
     */
    public static function bisect(string $message): array
    {
        $split = explode(self::LINE_END.self::LINE_END, $message, 2);
        $r['head'] = $split[0];
        $r['body'] = (!empty($split[1])) ? $split[1] : '';
        return $r;
    }

    /**
     * ヘッダをパースして配列で返す。
     *
     * @param string $header_raw
     * @return array
     */
    public static function header_parse(string $header_raw): array
    {
        $headers = [];
        $split = explode(self::LINE_END, $header_raw);
        for ($i = 0; $i < count($split); ++$i) {
            $split3 = explode(': ', $split[$i]);
            if (count($split3) === 2) {
                // ヘッダフィールドごとの処理 (RFC5322 - 2.2)
                $lastField = strtolower($split3[0]); // 処理中の最新のフィールド名
                $headers[$lastField] = $split3[1]; // KEY-VALUE形式で格納
            } else {
                // ヘッダフィールド形式にない改行や折り返しなど (RFC5322 - 2.2.3)
                // もとの行全体の両端を処理して最新のフィールドに改行を含めて追記
                if (isset($lastField)) {
                    $headers[$lastField] .= self::LINE_END.trim($split[$i]);
                }
            }
        }

        return $headers;
    }

    /**
     * ヘッダのフィールドを取得するときはメンバではなくこの関数を使うこと。
     *
     * @param string $field_name
     * @param array $header_fields_array
     * @return string
     */
    public static function getHeader(string $field_name, array $header_fields_array): string
    {
        $key = strtolower($field_name);
        return (isset($header_fields_array[$key])) ? $header_fields_array[$key] : '';
    }

    /**
     * メールアドレスが含まれるフィールド値を解析する。
     * 配列はnameキーに名前、addressキーにアドレスが入る。
     *
     * @param string $address_raw_field
     * @param string $name
     * @return string
     */
    public static function address_parse(string $address_raw_field, &$name = ''): string
    {
        $result = '';
        $name = ''; // optional

        if (preg_match("/[<(\[]*".self::PREG_EMAIL_ADDRESS_PATTERN."[>)\]]*/", $address_raw_field, $matches)) {
            // マッチ
            $name = trim(str_replace($matches[0], '', $address_raw_field));
            $result = trim($matches[0], "<>()[] \t\n\r\0\x0B");
        }

        return $result;
    }

    /**
     * マルチパートのボディを分割して返却する。
     *
     * @param string $content_type_with_boundary
     * @param string $multipart_body
     * @return array
     */
    public static function multipart_explode(string $content_type_with_boundary, string $multipart_body): array
    {
        $r = [];
        if (preg_match('/boundary=(.+)$/', $content_type_with_boundary, $matches)) {
            $boundary = '--'.trim($matches[1], "<>()[]\"':; \n\r");
            $multiparts = explode($boundary, $multipart_body);
            foreach ($multiparts as $part) {
                $trimmed = trim($part, "- \t\n\r\x0B");
                if (!empty($trimmed)) {
                    $r[] = $trimmed;
                }
            }
        }

        return $r;
    }

    /**
     * エンコードされたマルチバイトのヘッダー値をデコードする。
     * 返却値にはマルチバイト文字が含まれる。
     *
     * @param string $header_raw_value
     * @return string
     */
    public static function header_decode(string $header_raw_value): string
    {
        if (preg_match("/=\?(.+)\?([QB])\?(.+)\?=/", $header_raw_value, $matches)) {
            // 日本語とかマルチバイト文字列の処理
            // まずデコード
            if ($matches[2] === 'Q') {
                $decoded = quoted_printable_decode($matches[3]);
            } else {
                $decoded = base64_decode($matches[3]); // たいてい'B'でbase64
            }
            // 次に文字セット
            return mb_convert_encoding($decoded, 'UTF-8', $matches[1]);
        } else {
            // 正規表現にマッチしないエンコードなし（英語だけなど）はそのまま
            return $header_raw_value;
        }
    }
}
