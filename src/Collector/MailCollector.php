<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Collector;

use Datlechin\FlarumDebugbar\Support\Values;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Mail the request tried to send.
 *
 * Both mail events carry the very same {@see Email} object — `MessageSent`
 * exposes it through `getOriginalMessage()` — so the pair are matched by
 * object identity. Matching them positionally, as "the most recent entry
 * still marked as sending", goes wrong the moment two messages are in flight
 * at once, which is the normal shape of a notification fan-out.
 */
class MailCollector implements CollectorInterface, SubscribesToEvents
{
    protected const LIMIT = 100;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $messages = [];

    protected int $dropped = 0;

    public function name(): string
    {
        return 'mail';
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(MessageSending::class, $this->recordSending(...));
        $events->listen(MessageSent::class, $this->recordSent(...));
    }

    public function recordSending(MessageSending $event): void
    {
        $this->put($event->message, 'sending');
    }

    public function recordSent(MessageSent $event): void
    {
        $message = $event->sent->getOriginalMessage();

        if ($message instanceof Email) {
            $this->put($message, 'sent');
        }
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        return [
            'count' => count($this->messages) + $this->dropped,
            'dropped' => $this->dropped,
            'messages' => array_values($this->messages),
        ];
    }

    protected function put(Email $message, string $status): void
    {
        $id = spl_object_id($message);

        if (isset($this->messages[$id])) {
            $this->messages[$id]['status'] = $status;
            $this->messages[$id]['time'] = microtime(true);

            return;
        }

        if (count($this->messages) >= self::LIMIT) {
            $this->dropped++;

            return;
        }

        $this->messages[$id] = [
            'status' => $status,
            'time' => microtime(true),
            'subject' => Values::stringify($message->getSubject() ?? ''),
            'from' => $this->addresses($message->getFrom()),
            'to' => $this->addresses($message->getTo()),
            'cc' => $this->addresses($message->getCc()),
            'bcc' => $this->addresses($message->getBcc()),
            'replyTo' => $this->addresses($message->getReplyTo()),
            'body' => Values::truncate(Values::printable($this->body($message)), 4000),
        ];
    }

    /**
     * @param Address[] $addresses
     * @return list<string>
     */
    protected function addresses(array $addresses): array
    {
        return array_map(
            fn (Address $address) => $address->getName() !== ''
                ? $address->getName().' <'.$address->getAddress().'>'
                : $address->getAddress(),
            $addresses
        );
    }

    /**
     * The plain-text body where there is one; otherwise the HTML, which at
     * least shows what was written even if it reads awkwardly.
     */
    protected function body(Email $message): string
    {
        $text = $message->getTextBody();

        if (is_string($text) && $text !== '') {
            return $text;
        }

        $html = $message->getHtmlBody();

        return is_string($html) ? $html : '';
    }
}
