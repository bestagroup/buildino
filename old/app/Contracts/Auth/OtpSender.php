<?php
namespace App\Contracts\Auth;

interface OtpSender
{
    public function send(string $identifier, string $channel, string $code): void;
}
