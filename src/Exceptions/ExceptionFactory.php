<?php

namespace Imap\Exceptions;

class ExceptionFactory
{
    public function getExceptionFromImapError(): ImapException
    {
        //TODO [exception] return different exceptions according to imap_errors() or imap_last_error()
        return new GeneralImapException(var_export(imap_last_error(), true));
    }
}