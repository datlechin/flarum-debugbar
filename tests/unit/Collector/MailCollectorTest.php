<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit\Collector;

use Datlechin\FlarumDebugbar\Collector\MailCollector;
use Datlechin\FlarumDebugbar\Tests\unit\MakesHttpMessages;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;

class MailCollectorTest extends TestCase
{
    use MakesHttpMessages;

    private function email(string $subject, string $to): Email
    {
        return (new Email())
            ->subject($subject)
            ->from('forum@example.com')
            ->to($to)
            ->text('Body of '.$subject);
    }

    private function sent(Email $email): MessageSent
    {
        $envelope = new Envelope($email->getFrom()[0], $email->getTo());

        return new MessageSent(new SentMessage(new SymfonySentMessage($email, $envelope)));
    }

    private function collect(MailCollector $collector): array
    {
        return $collector->collect($this->request(), $this->response());
    }

    #[Test]
    public function it_records_a_message_as_it_is_sent(): void
    {
        $collector = new MailCollector();
        $email = $this->email('Welcome', 'alice@example.com');

        $collector->recordSending(new MessageSending($email));

        $message = $this->collect($collector)['messages'][0];

        $this->assertSame('sending', $message['status']);
        $this->assertSame('Welcome', $message['subject']);
        $this->assertSame(['alice@example.com'], $message['to']);
        $this->assertSame(['forum@example.com'], $message['from']);
        $this->assertSame('Body of Welcome', $message['body']);
    }

    #[Test]
    public function it_marks_the_same_message_as_sent_rather_than_recording_it_twice(): void
    {
        $collector = new MailCollector();
        $email = $this->email('Welcome', 'alice@example.com');

        $collector->recordSending(new MessageSending($email));
        $collector->recordSent($this->sent($email));

        $data = $this->collect($collector);

        $this->assertSame(1, $data['count']);
        $this->assertSame('sent', $data['messages'][0]['status']);
    }

    #[Test]
    public function it_pairs_messages_by_identity_rather_than_by_arrival_order(): void
    {
        // A notification fan-out has several messages in flight at once. The
        // obvious implementation — "update the most recent entry still marked
        // as sending" — attributes each `sent` to the wrong message the
        // moment the events interleave.
        $collector = new MailCollector();

        $first = $this->email('First', 'alice@example.com');
        $second = $this->email('Second', 'bob@example.com');

        $collector->recordSending(new MessageSending($first));
        $collector->recordSending(new MessageSending($second));
        $collector->recordSent($this->sent($first));

        $messages = array_column($this->collect($collector)['messages'], 'status', 'subject');

        $this->assertSame(['First' => 'sent', 'Second' => 'sending'], $messages);
    }

    #[Test]
    public function it_records_a_message_it_only_saw_being_sent(): void
    {
        $collector = new MailCollector();
        $collector->recordSent($this->sent($this->email('Digest', 'carol@example.com')));

        $data = $this->collect($collector);

        $this->assertSame(1, $data['count']);
        $this->assertSame('sent', $data['messages'][0]['status']);
    }

    #[Test]
    public function it_keeps_every_kind_of_recipient(): void
    {
        $collector = new MailCollector();

        $email = $this->email('Notice', 'alice@example.com')
            ->cc('carol@example.com')
            ->bcc('dave@example.com')
            ->replyTo('noreply@example.com');

        $collector->recordSending(new MessageSending($email));

        $message = $this->collect($collector)['messages'][0];

        $this->assertSame(['carol@example.com'], $message['cc']);
        $this->assertSame(['dave@example.com'], $message['bcc']);
        $this->assertSame(['noreply@example.com'], $message['replyTo']);
    }

    #[Test]
    public function it_shows_a_name_alongside_an_address_when_there_is_one(): void
    {
        $collector = new MailCollector();

        $email = (new Email())->subject('Hi')->from('forum@example.com')->to(new \Symfony\Component\Mime\Address('alice@example.com', 'Alice'));

        $collector->recordSending(new MessageSending($email));

        $this->assertSame(['Alice <alice@example.com>'], $this->collect($collector)['messages'][0]['to']);
    }

    #[Test]
    public function it_falls_back_to_the_html_body(): void
    {
        $collector = new MailCollector();

        $email = (new Email())->subject('Hi')->from('forum@example.com')->to('alice@example.com')->html('<p>Hello</p>');

        $collector->recordSending(new MessageSending($email));

        $this->assertSame('<p>Hello</p>', $this->collect($collector)['messages'][0]['body']);
    }

    #[Test]
    public function it_records_nothing_when_no_mail_was_sent(): void
    {
        $data = $this->collect(new MailCollector());

        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['messages']);
    }
}
