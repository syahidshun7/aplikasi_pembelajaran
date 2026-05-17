<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ServerErrorAlert extends Mailable
{
    use Queueable, SerializesModels;

    public string $exceptionClass;
    public string $messageText;
    public string $url;
    public string $traceId;
    public int $status;

    /**
     * Create a new message instance.
     */
    public function __construct(Throwable $e, string $url, int $status, string $traceId)
    {
        $this->exceptionClass = get_class($e);
        $this->messageText = (string) $e->getMessage();
        $this->url = $url;
        $this->traceId = $traceId;
        $this->status = $status;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject("ALERT: {$this->status} at {$this->url}")
            ->view('emails.server-error-alert');
    }
}
