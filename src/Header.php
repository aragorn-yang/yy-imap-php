<?php

namespace Imap;


class Header
{
    /**
     * @var \stdClass
     */
    public $headers;
    /**
     * @var Mail
     */
    private $mail;

    /**
     * @var string
     */
    public $date;
    /**
     * @var string
     */
    public $subject = '';
    /**
     * @var array
     */
    public $from = [];
    /**
     * @var array
     */
    public $to = [];
    /**
     * @var array
     */
    public $cc = [];
    /**
     * @var array
     */
    public $bcc = [];
    /**
     * @var array
     */
    public $replyTo = [];
    /**
     * @var array
     */
    public $sender = [];
    /**
     * @var int
     */
    public $messageId = 0;

    public function __construct(Mail $mail)
    {
        $this->mail = $mail;
    }

    public function parseHeaders(): void
    {
        $rawHeaders = \imap_fetchheader($this->mail->getStream(), $this->mail->id, FT_UID);
        $this->headers = \imap_rfc822_parse_headers($rawHeaders);
        $this->messageId = $this->headers->message_id ?? 0;
        if (isset($this->headers->date)) {
            $this->date = date('Y-m-d H:i:s', strtotime($this->headers->date));
        }
        if (isset($this->headers->subject)) {
            $this->subject = $this->decodeMimeHeader($this->headers->subject, $this->getServerEncoding());
        }
        if (isset($this->headers->from)) {
            $this->from = $this->beautifyPersons($this->headers->from);
        }
        if (isset($this->headers->to)) {
            $this->to = $this->beautifyPersons($this->headers->to);
        }
        if (isset($this->headers->cc)) {
            $this->cc = $this->beautifyPersons($this->headers->cc);
        }
        if (isset($this->headers->bcc)) {
            $this->bcc = $this->beautifyPersons($this->headers->bcc);
        }
        if (isset($this->headers->reply_to)) {
            $this->replyTo = $this->beautifyPersons($this->headers->reply_to);
        }
        if (isset($this->headers->sender)) {
            $this->sender = $this->beautifyPersons($this->headers->sender);
        }
    }


    private function beautifyPersons(array $persons): array
    {
        $beautified = [];
        foreach ($persons as $person) {
            if (!empty($person->mailbox) && !empty($person->host)) {
                $name = '';
                $email = strtolower($person->mailbox . '@' . $person->host);
                if (isset($person->personal)) {
                    $name = $this->decodeMimeHeader($person->personal, $this->getServerEncoding());
                }
                $beautified[$email] = $name;
            }
        }

        return $beautified;
    }

    private function decodeMimeHeader($encoded, string $charset = 'utf-8'): string
    {
        $decoded = '';
        $elements = \imap_mime_header_decode($encoded);
        foreach ($elements as $element) {
            if ($element->charset === 'default') {
                $element->charset = 'iso-8859-1';
            }
            $decoded .= Helper::convertEncoding($element->text, $element->charset, $charset);
        }

        return $decoded;
    }

    private function getServerEncoding(): string
    {
        return $this->mail->getServerEncoding();
    }

}