<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\Manager;

use App\Logic\Settings\MailTemplate\MailTemplateProcessorInterface;
use App\Logic\Settings\MailTemplate\MailTemplateProviderInterface;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManager;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use PHPUnit\Framework\TestCase;

final class MailTemplateManagerTest extends TestCase
{
    public function testResolveFallsBackToDefaultTextWhenNothingIsStored(): void
    {
        $provider = $this->createStub(MailTemplateProviderInterface::class);
        $provider->method('find')->willReturn(null);
        $processor = $this->createStub(MailTemplateProcessorInterface::class);

        $key = MailTemplateKey::MembershipApplicationApproved;
        $resolved = (new MailTemplateManager($provider, $processor))->resolve($key);

        self::assertSame($key->defaultSubject(), $resolved->subject);
        self::assertSame($key->defaultBody(), $resolved->body);
    }

    public function testResolveReturnsTheStoredCustomization(): void
    {
        $key = MailTemplateKey::MembershipApplicationApproved;
        $stored = new MailTemplate($key, 'Eigener Betreff', 'Eigener Text');
        $provider = $this->createStub(MailTemplateProviderInterface::class);
        $provider->method('find')->willReturn($stored);
        $processor = $this->createStub(MailTemplateProcessorInterface::class);

        $resolved = (new MailTemplateManager($provider, $processor))->resolve($key);

        self::assertSame($stored, $resolved);
    }

    public function testResolveAllReturnsExactlyOneEntryPerKeyEvenWhenNoneAreStored(): void
    {
        $provider = $this->createStub(MailTemplateProviderInterface::class);
        $provider->method('findAll')->willReturn([]);
        $processor = $this->createStub(MailTemplateProcessorInterface::class);

        $all = (new MailTemplateManager($provider, $processor))->resolveAll();

        self::assertCount(count(MailTemplateKey::cases()), $all);
        foreach ($all as $index => $template) {
            self::assertSame(MailTemplateKey::cases()[$index], $template->key);
        }
    }

    public function testResolveAllMixesStoredCustomizationsWithDefaults(): void
    {
        $customized = new MailTemplate(MailTemplateKey::MembershipApplicationApproved, 'Eigener Betreff', 'Eigener Text');
        $provider = $this->createStub(MailTemplateProviderInterface::class);
        $provider->method('findAll')->willReturn([$customized]);
        $processor = $this->createStub(MailTemplateProcessorInterface::class);

        $all = (new MailTemplateManager($provider, $processor))->resolveAll();

        self::assertSame($customized, $this->findByKey($all, MailTemplateKey::MembershipApplicationApproved));
        self::assertTrue($this->findByKey($all, MailTemplateKey::MembershipApplicationSubmittedNotification)->isDefault());
    }

    /**
     * @param list<MailTemplate> $templates
     */
    private function findByKey(array $templates, MailTemplateKey $key): MailTemplate
    {
        foreach ($templates as $template) {
            if ($template->key === $key) {
                return $template;
            }
        }
        self::fail(sprintf('Keine Vorlage für "%s" gefunden.', $key->value));
    }
}
