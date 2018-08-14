<?php

namespace Imap;


use Imap\Exceptions\ExceptionFactory;

class Mailbox
{
    /**
     * The IMAP stream
     *
     * @var resource
     */
    private $stream;
    /**
     * A mailbox name consists of a server and a mailbox path on this server.
     *
     * @var string
     */
    private $mailbox;
    /**
     * The user name
     *
     * @var string
     */
    private $username;
    /**
     * The password associated with the <i>username</i>
     *
     * @var string
     */
    private $password;
    /**
     * The <i>options</i> are a bit mask with one or more of
     * the following:
     * <b>OP_READONLY</b> - Open mailbox read-only
     * <b>OP_ANONYMOUS</b> - Don't use or update a `.newsrc` for news (NNTP only)
     * <b>OP_HALFOPEN</b> - For IMAP and NNTP names, open a connection but don't open a mailbox.
     * <b>CL_EXPUNGE</b> - Expunge mailbox automatically upon mailbox close (see also imap_delete() and imap_expunge())
     * <b>OP_DEBUG</b> - Debug protocol negotiations
     * <b>OP_SHORTCACHE</b> - Short (elt-only) caching
     * <b>OP_SILENT</b> - Don't pass up events (internal use)
     * <b>OP_PROTOTYPE</b> - Return driver prototype
     * <b>OP_SECURE</b> - Don't do non-secure authentication
     *
     * @var int
     */
    private $options = 0;

    /**
     * Number of maximum connect attempts
     *
     * @var int
     */
    private $numOfRetries = 0;

    /**
     * @var bool
     */
    private $gssApiDisabled = false;
    /**
     * @var bool
     */
    private $ntlmDisabled = false;
    /**
     * @var string
     */
    private $serverEncoding = 'UTF-8';

    public function __destruct()
    {
        $this->disconnect();
    }

    public function getStream()
    {
        if (!$this->stream) {
            $this->connect();
        }
        return $this->stream;
    }

    public function connect(): void
    {
        $params = $this->getParams();
        $this->stream = \imap_open($this->getMailbox(), $this->username, $this->password, $this->options,
            $this->numOfRetries, $params);
        if (!$this->stream) {
            throw (new ExceptionFactory)->getExceptionFromImapError();
        }
    }

    public function disconnect(bool $expungeOnDisconnecting = true): void
    {
        if ($this->stream && \is_resource($this->stream)) {
            \imap_close($this->stream, $expungeOnDisconnecting ? CL_EXPUNGE : 0);
        }
    }

    public function search(string $criteria = 'ALL'): array
    {
        $mailIds = \imap_search($this->getStream(), $criteria, SE_UID);

        return $mailIds ?: [];
    }

    public function getMail(int $mailId, bool $markAsSeen = true): Mail
    {
        $mail = new Mail($this->getStream(), $mailId, $this->serverEncoding);
        $mail->fetchHeader();
        $mail->fetchBody($markAsSeen);

        return $mail;
    }

    public function delete(int $mailId): bool
    {
        return \imap_delete($this->getStream(), $mailId, FT_UID);
    }

    public function getListingFolders(): array
    {
        return \imap_list($this->getStream(), $this->getMailbox(), '*');
    }

    public function switchMailbox($mailbox = ''): void
    {
        $this->setMailbox($mailbox);
        if (!\imap_reopen($this->stream, $this->getMailbox())) {
            throw (new ExceptionFactory)->getExceptionFromImapError();
        }
    }

    public function getMailbox(): string
    {
        return $this->mailbox;
    }

    public function setMailbox(string $mailbox): Mailbox
    {
        /**
         * e.g. "已删除" will be converted into "&XfJSIJZk-"
         */
        $this->mailbox = \mb_convert_encoding($mailbox, 'UTF7-IMAP', 'UTF-8');

        return $this;
    }

    public function setUsername(string $username): Mailbox
    {
        $this->username = $username;

        return $this;
    }

    public function setPassword(string $password): Mailbox
    {
        $this->password = $password;

        return $this;
    }

    public function setNumOfRetries(int $numOfRetries = 0): Mailbox
    {
        $this->numOfRetries = $numOfRetries;

        return $this;
    }

    public function setOptions(int $options): Mailbox
    {
        $this->options = $options;

        return $this;
    }

    public function disableGSSAPI(): Mailbox
    {
        $this->gssApiDisabled = true;

        return $this;
    }

    public function disableNTLM(): Mailbox
    {
        $this->ntlmDisabled = true;

        return $this;
    }

    public function setServerEncoding(string $serverEncoding = ''): Mailbox
    {
        $this->serverEncoding = strtoupper($serverEncoding);

        return $this;
    }

    private function getParams(): array
    {
        $disabledAuth = [];
        if ($this->gssApiDisabled) {
            $disabledAuth[] = 'GSSAPI';
        }
        if ($this->ntlmDisabled) {
            $disabledAuth[] = 'NTLM';
        }
        if (\count($disabledAuth) > 1) {
            return ['DISABLE_AUTHENTICATOR' => $disabledAuth];
        }

        if (\count($disabledAuth) === 1) {
            return ['DISABLE_AUTHENTICATOR' => $disabledAuth[0]];
        }
        return [];
    }
}