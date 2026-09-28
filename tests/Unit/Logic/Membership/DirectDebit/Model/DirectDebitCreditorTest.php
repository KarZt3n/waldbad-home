<?php

namespace App\Tests\Unit\Logic\Membership\DirectDebit\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use PHPUnit\Framework\TestCase;

final class DirectDebitCreditorTest extends TestCase
{
    public function testNormalizesAndAcceptsValidData(): void
    {
        $creditor = new DirectDebitCreditor('Waldbad Borkheide e.V.', 'de98 zzz0 9999 9999 99', 'DE02 1203 0000 0000 2020 51', 'byladem1001');

        self::assertSame('DE98ZZZ09999999999', $creditor->creditorId);
        self::assertSame('DE02120300000000202051', $creditor->iban);
        self::assertSame('BYLADEM1001', $creditor->bic);
        self::assertTrue($creditor->isComplete());
    }

    public function testBicIsOptionalForCompleteness(): void
    {
        self::assertTrue((new DirectDebitCreditor('Verein', 'DE98ZZZ09999999999', 'DE02120300000000202051', null))->isComplete());
        self::assertFalse(DirectDebitCreditor::empty()->isComplete());
    }

    public function testRejectsCreditorIdWithWrongCheckDigits(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        new DirectDebitCreditor('Verein', 'DE97ZZZ09999999999', null, null);
    }

    public function testRejectsInvalidIban(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        new DirectDebitCreditor('Verein', null, 'DE02120300000000202052', null);
    }

    public function testRejectsInvalidBic(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        new DirectDebitCreditor('Verein', null, null, 'BYLA');
    }
}
