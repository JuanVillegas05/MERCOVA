<?php

namespace App\Mail\Transport;

use Illuminate\Http\Client\Factory as HttpFactory;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Throwable;

/**
 * Envía correos con la API HTTP de Brevo (POST /v3/smtp/email) en vez de
 * SMTP. Render (plan gratuito) bloquea los puertos SMTP salientes, pero no
 * HTTPS. Usa el cliente HTTP de Laravel para tener timeouts explícitos y
 * poder simularlo con Http::fake() en los tests.
 *
 * Remitente: el MAIL_FROM_ADDRESS debe estar verificado en Brevo
 * (Senders, Domains & Dedicated IPs → Senders).
 */
class BrevoTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $apiKey,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (blank($this->apiKey)) {
            throw new TransportException('Falta BREVO_API_KEY para enviar correos con Brevo.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());

        try {
            $response = $this->http
                ->withHeaders(['api-key' => (string) $this->apiKey])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post(self::ENDPOINT, $this->payload($email));
        } catch (Throwable $e) {
            throw new TransportException('No se pudo conectar con Brevo: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new TransportException(sprintf(
                'Brevo rechazó el correo (HTTP %d): %s',
                $response->status(),
                $response->json('message') ?? $response->body(),
            ));
        }

        $messageId = $response->json('messageId');

        if (is_string($messageId)) {
            $message->setMessageId($messageId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Email $email): array
    {
        $sender = $email->getFrom()[0] ?? null;

        if ($sender === null) {
            throw new TransportException('El correo no tiene remitente (MAIL_FROM_ADDRESS).');
        }

        return array_filter([
            'sender' => $this->address($sender),
            'to' => $this->addresses($email->getTo()),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'replyTo' => isset($email->getReplyTo()[0]) ? $this->address($email->getReplyTo()[0]) : null,
            'subject' => (string) $email->getSubject(),
            'htmlContent' => $email->getHtmlBody() !== null ? (string) $email->getHtmlBody() : null,
            'textContent' => $email->getTextBody() !== null ? (string) $email->getTextBody() : null,
            'attachment' => array_map(fn ($attachment) => [
                'name' => $attachment->getFilename() ?? 'adjunto',
                'content' => base64_encode($attachment->getBody()),
            ], $email->getAttachments()),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->address($address), $addresses);
    }

    /**
     * @return array{email: string, name?: string}
     */
    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], fn (string $value) => $value !== '');
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
