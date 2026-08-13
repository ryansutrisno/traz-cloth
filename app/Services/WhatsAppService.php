<?php

namespace App\Services;

interface WhatsAppService
{
    public function send(string $phone, string $message): void;
}
