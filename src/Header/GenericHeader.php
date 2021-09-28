<?php

/**
 * @see       https://github.com/laminas/laminas-mail for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mail/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mail/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Mail\Header;

use Laminas\Mime\Mime;

class GenericHeader implements HeaderInterface, UnstructuredInterface
{
    /**
     * @var string
     */
    protected $fieldName = null;

    /**
     * @var string
     */
    protected $fieldValue = null;

    /**
     * Header encoding
     *
     * @var null|string
     */
    protected $encoding;

    /**
     * @param string $headerLine
     * @return GenericHeader
     */
    public static function fromString($headerLine)
    {
        list($name, $value) = self::splitHeaderLine($headerLine);
        $value  = HeaderWrap::mimeDecodeValue($value);
        $header = new static($name, $value);

        return $header;
    }

    /**
     * Splits the header line in `name` and `value` parts.
     *
     * @param string $headerLine
     * @return string[] `name` in the first index and `value` in the second.
     * @throws Exception\InvalidArgumentException If header does not match with the format ``name:value``
     */
    public static function splitHeaderLine($headerLine)
    {
        if (mb_detect_encoding($headerLine, ['ASCII', 'UTF-8'], true) === 'UTF-8') {
            // replace umlauts for validity check in ASCII
            $search = ['À','Á','Â','Ã','Ä','Å','Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ð','Ñ','Ò','Ó','Ô','Õ','Ö','×','Ø','Ù','Ú','Û','Ü','Ý','Þ','ß','à','á','â','ã','ä','å','æ','ç','è','é','ê','ë','ì','í','î','ï','ð','ñ','ò','ó','ô','õ','ö','÷','ø','ù','ú','û','ü','ý','þ','ÿ','Ă','ă','Ą','ą','Ć','ć','Č','č','Ď','ď','Đ','đ','Ę','ę','Ě','ě','Ĺ','ĺ','Ľ','ľ','Ł','ł','Ń','ń','Ň','ň','Ő','ő','Œ','œ','Ŕ','ŕ','Ř','ř','Ś','ś','Ş','ş','Š','š','Ţ','ţ','Ť','ť','Ů','ů','Ű','ű','Ÿ','Ź','ź','Ż','ż','Ž','ž','ƒ'];
            $replace = ['A','A','A','A','AE','A','AE','C','E','E','E','E','I','I','I','I','ETH','N','O','O','O','O','OE','x','O','U','U','U','UE','Y','THORN','ss','a','a','a','a','ae','a','ae','c','e','e','e','e','i','i','i','i','o','n','o','o','o','oe','/','o','u','u','u','ue','y','thorn','y','A','a','C','c','C','c','D','d','D','d','E','e','E','e','L','l','L','l','L','l','N','n','N','n','OE','oe','OE','oe','R','r','R','r','S','s','S','s','S','s','T','t','T','t','U','u','UE','ue','Y','Z','z','Z','z','Z','z','f'];
            $headerLine = str_replace($search, $replace, $headerLine);
        }
        $parts = explode(':', $headerLine, 2);
        if (count($parts) !== 2) {
            throw new Exception\InvalidArgumentException('Header must match with the format "name:value"');
        }

        if (! HeaderName::isValid($parts[0])) {
            throw new Exception\InvalidArgumentException('Invalid header name detected');
        }

        if (! HeaderValue::isValid($parts[1])) {
            throw new Exception\InvalidArgumentException('Invalid header value detected');
        }

        $parts[1] = ltrim($parts[1]);

        return $parts;
    }

    /**
     * Constructor
     *
     * @param string $fieldName  Optional
     * @param string $fieldValue Optional
     */
    public function __construct($fieldName = null, $fieldValue = null)
    {
        if ($fieldName) {
            $this->setFieldName($fieldName);
        }

        if ($fieldValue !== null) {
            $this->setFieldValue($fieldValue);
        }
    }

    /**
     * Set header name
     *
     * @param  string $fieldName
     * @return GenericHeader
     * @throws Exception\InvalidArgumentException;
     */
    public function setFieldName($fieldName)
    {
        if (! is_string($fieldName) || empty($fieldName)) {
            throw new Exception\InvalidArgumentException('Header name must be a string');
        }

        // Pre-filter to normalize valid characters, change underscore to dash
        $fieldName = str_replace(' ', '-', ucwords(str_replace(['_', '-'], ' ', $fieldName)));

        if (! HeaderName::isValid($fieldName)) {
            throw new Exception\InvalidArgumentException(
                'Header name must be composed of printable US-ASCII characters, except colon.'
            );
        }

        $this->fieldName = $fieldName;
        return $this;
    }

    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * Set header value
     *
     * @param  string $fieldValue
     * @return GenericHeader
     * @throws Exception\InvalidArgumentException;
     */
    public function setFieldValue($fieldValue)
    {
        $fieldValue = (string) $fieldValue;

        if (! HeaderWrap::canBeEncoded($fieldValue)) {
            throw new Exception\InvalidArgumentException(
                'Header value must be composed of printable US-ASCII characters and valid folding sequences.'
            );
        }

        $this->fieldValue = $fieldValue;
        $this->encoding   = null;

        return $this;
    }

    public function getFieldValue($format = HeaderInterface::FORMAT_RAW)
    {
        if (HeaderInterface::FORMAT_ENCODED === $format) {
            return HeaderWrap::wrap($this->fieldValue, $this);
        }

        return $this->fieldValue;
    }

    public function setEncoding($encoding)
    {
        if ($encoding === $this->encoding) {
            return $this;
        }

        if ($encoding === null) {
            $this->encoding = null;
            return $this;
        }

        $encoding = strtoupper($encoding);
        if ($encoding === 'UTF-8') {
            $this->encoding = $encoding;
            return $this;
        }

        if ($encoding === 'ASCII' && Mime::isPrintable($this->fieldValue)) {
            $this->encoding = $encoding;
            return $this;
        }

        $this->encoding = null;

        return $this;
    }

    public function getEncoding()
    {
        if (! $this->encoding) {
            $this->encoding = Mime::isPrintable($this->fieldValue) ? 'ASCII' : 'UTF-8';
        }

        return $this->encoding;
    }

    public function toString()
    {
        $name = $this->getFieldName();
        if (empty($name)) {
            throw new Exception\RuntimeException('Header name is not set, use setFieldName()');
        }
        $value = $this->getFieldValue(HeaderInterface::FORMAT_ENCODED);

        return $name . ': ' . $value;
    }
}
