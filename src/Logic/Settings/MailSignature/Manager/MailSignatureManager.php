<?php

namespace App\Logic\Settings\MailSignature\Manager;

use App\Logic\Settings\MailSignature\Exception\MailSignatureNotFoundException;
use App\Logic\Settings\MailSignature\MailSignatureProcessorInterface;
use App\Logic\Settings\MailSignature\MailSignatureProviderInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;

readonly class MailSignatureManager implements MailSignatureManagerInterface
{
    public function __construct(
        private MailSignatureProviderInterface $provider,
        private MailSignatureProcessorInterface $processor,
    ) {
    }

    public function get(string $id): MailSignature
    {
        return $this->provider->find($id) ?? throw new MailSignatureNotFoundException($id);
    }

    public function find(string $id): ?MailSignature
    {
        return $this->provider->find($id);
    }

    public function list(): array
    {
        return $this->provider->findAll();
    }

    public function save(MailSignature $signature): MailSignature
    {
        return $this->processor->save($signature);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
