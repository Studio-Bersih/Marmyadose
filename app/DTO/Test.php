<?php

namespace app\DTO;

class Test {
    public string $status;
    public string $message;
    public string $redirectTo;

    public function __construct(string $status, string $message, $redirectTo) {
        $this->status = $status;
        $this->message = $message;
        $this->redirectTo = $redirectTo;
    }
}