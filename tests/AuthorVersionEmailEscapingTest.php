<?php

declare(strict_types=1);

use APP\plugins\generic\authorVersion\classes\mail\mailables\DeletedVersionNotification;
use APP\plugins\generic\authorVersion\classes\mail\mailables\SubmittedVersionNotification;
use APP\publication\Publication;
use APP\server\Server;
use APP\submission\Submission;
use PKP\mail\Mailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\NullTransport;

class AuthorVersionEmailEscapingTest extends TestCase
{
    public function testJustificationsRemainTextInCompiledNotificationBodies(): void
    {
        $publication = new Publication();
        $submission = $this->createMock(Submission::class);
        $submission->method('getCurrentPublication')->willReturn($publication);
        $context = new Server();
        $mailer = new Mailer('test', new NullTransport());
        $text = '<a href="https://example.test">Link</a><img src="https://example.test/image"> & "quoted" \'apostrophe\'';

        foreach ([
            [SubmittedVersionNotification::class, 'versionJustification'],
            [DeletedVersionNotification::class, 'deletingJustification'],
        ] as [$class, $field]) {
            $variables = [$field => $text];
            $notification = new $class($context, $submission, $variables);
            $html = $mailer->compileParams('<p>{$' . $field . '}</p>', $notification->viewData);
            self::assertStringNotContainsString('<a ', $html);
            self::assertStringNotContainsString('<img ', $html);
            self::assertSame($text, html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            self::assertSame($text, $variables[$field], 'Encoding must not alter the persisted/input text.');
        }
    }
}
