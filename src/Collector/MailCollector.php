<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;

class MailCollector extends DataCollector implements Renderable
{
    protected array $emails = [];

    public function onMessageSending(MessageSending $event): void
    {
        $message = $event->message;

        $this->emails[] = [
            'status' => 'sending',
            'subject' => $message->getSubject() ?? '(no subject)',
            'to' => $this->formatAddresses($message->getTo()),
            'from' => $this->formatAddresses($message->getFrom()),
            'time' => microtime(true),
        ];
    }

    public function onMessageSent(MessageSent $event): void
    {
        $message = $event->message;

        // Update the last entry if it was "sending"
        $lastIndex = count($this->emails) - 1;

        if ($lastIndex >= 0 && $this->emails[$lastIndex]['status'] === 'sending') {
            $this->emails[$lastIndex]['status'] = 'sent';
            $this->emails[$lastIndex]['time'] = microtime(true);
        } else {
            $this->emails[] = [
                'status' => 'sent',
                'subject' => $message->getSubject() ?? '(no subject)',
                'to' => $this->formatAddresses($message->getTo()),
                'from' => $this->formatAddresses($message->getFrom()),
                'time' => microtime(true),
            ];
        }
    }

    /**
     * @param Address[] $addresses
     */
    protected function formatAddresses(array $addresses): string
    {
        return implode(', ', array_map(
            fn (Address $addr) => $addr->getName() ? "{$addr->getName()} <{$addr->getAddress()}>" : $addr->getAddress(),
            $addresses
        ));
    }

    public function collect(): array
    {
        $messages = [];

        foreach ($this->emails as $email) {
            $label = $email['status'] === 'sent' ? 'info' : 'warning';
            $status = strtoupper($email['status']);

            $messages[] = [
                'message' => "[{$status}] {$email['subject']} → {$email['to']}",
                'message_html' => null,
                'is_string' => true,
                'label' => $label,
                'time' => $email['time'],
            ];
        }

        return [
            'count' => count($this->emails),
            'messages' => $messages,
        ];
    }

    public function getName(): string
    {
        return 'mail';
    }

    public function getWidgets(): array
    {
        return [
            'mail' => [
                'icon' => 'inbox',
                'title' => 'Mail',
                'widget' => 'PhpDebugBar.Widgets.MessagesWidget',
                'map' => 'mail.messages',
                'default' => '[]',
            ],
            'mail:badge' => [
                'map' => 'mail.count',
                'default' => 0,
            ],
        ];
    }
}
