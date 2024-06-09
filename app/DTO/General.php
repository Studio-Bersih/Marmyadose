<?php

namespace app\DTO;

class General {
    public string $status;
    public string $message;

    public function __construct(string $status, string $message) {
        $this->status = $status;
        $this->message = $message;
    }
}