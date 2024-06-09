<?php

namespace app\DTO;

class Responses {
    public string $status;
    public string $message;
    public $data;

    public function __construct(string $status, string $message, $data = null) {
        $this->status = $status;
        $this->message = $message;
        $this->data = $data;
    }
}