<?php

namespace Imap;

class Mail
{
    /**
     * @var resource
     */
    private $stream;
    /**
     * @var int
     */
    public $id;
    /**
     * @var string
     */
    public $plainText;
    /**
     * @var string
     */
    public $htmlText;
    /**
     * @var string
     */
    private $serverEncoding;
    /**
     * @var Header
     */
    private $header;

    public function __construct($stream, $mailId, $serverEncoding)
    {
        $this->stream = $stream;
        $this->id = $mailId;
        $this->serverEncoding = $serverEncoding;
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    public function fetchHeader(): void
    {
        $this->header = new Header($this);
        $this->header->parseHeaders();
    }

    public function fetchBody(bool $markAsSeen = true): void
    {
        $structure = imap_fetchstructure($this->getStream(), $this->id, FT_UID);
        if (empty($structure->parts)) {
            $this->processMailPart($structure, 0, $markAsSeen);

            return;
        }
        foreach ($structure->parts as $partNum => $partStructure) {
            $this->processMailPart($partStructure, $partNum + 1, $markAsSeen);
        }
    }

    private function processMailPart($partStructure, $partNum = 0, bool $markAsSeen = true): void
    {
        $options = FT_UID;
        if (!$markAsSeen) {
            $options |= FT_PEEK;
        }
        $data = $partNum ? imap_fetchbody($this->getStream(), $this->id, $partNum,
            $options) : imap_body($this->getStream(), $this->id, $options);

        $data = $this->decodeBodyPart($data, $partStructure->encoding);
        $params = $this->getParams($partStructure);
        if (!empty($params['charset'])) {
            $data = Helper::convertEncoding($data, $params['charset'], $this->serverEncoding);
        }
        if ($partStructure->type === TYPETEXT && $data) {
            if (strtolower($partStructure->subtype) === 'plain') {
                $this->plainText .= $data;
            } else {
                $this->htmlText .= $data;
            }
        } elseif ($partStructure->type === TYPEMESSAGE && $data) {
            $this->plainText .= trim($data);
        }
        if (!empty($partStructure->parts)) {
            foreach ($partStructure->parts as $subPartNum => $subPartStructure) {
                if ($partStructure->type === TYPEMESSAGE && $partStructure->subtype === 'RFC822') {
                    $this->processMailPart($subPartStructure, $partNum, $markAsSeen);
                } else {
                    $this->processMailPart($subPartStructure, $partNum . '.' . ($subPartNum + 1), $markAsSeen);
                }
            }
        }
    }


    private function getParams($partStructure): array
    {
        if (empty($partStructure->parameters)) {
            return [];
        }
        $params = [];
        if ($partStructure->ifparameters) {
            foreach ($partStructure->parameters as $param) {
                $params[strtolower($param->attribute)] = $param->value;
            }
        }
        //TODO [dparameters] http://php.net/manual/en/function.imap-fetchstructure.php
        //if ($partStructure->ifdparameters) {
        //    foreach ($partStructure->dparameters as $param) {
        //        $param->attribute;
        //        $param->value;
        //    }
        //}
        return $params;
    }

    private function decodeBodyPart($data, $encoding)
    {
        if ($encoding === ENC7BIT) {
            return imap_utf7_decode($data);
        }

        if ($encoding === ENC8BIT) {
            return imap_utf8($data);
        }

        if ($encoding === ENCBINARY) {
            return imap_binary($data);
        }

        if ($encoding === ENCBASE64) {
            return imap_base64($data);
        }

        if ($encoding === ENCQUOTEDPRINTABLE) {
            return quoted_printable_decode($data);
        }

        if ($encoding === ENCOTHER) {
            return $data;
        }

        return $data;
    }

}