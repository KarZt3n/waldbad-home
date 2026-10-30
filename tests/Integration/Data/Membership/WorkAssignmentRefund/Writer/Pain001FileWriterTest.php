<?php

namespace App\Tests\Integration\Data\Membership\WorkAssignmentRefund\Writer;

use App\Data\Membership\WorkAssignmentRefund\Writer\Pain001FileWriter;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use App\Logic\Membership\WorkAssignmentRefund\Model\CreditTransferBatch;
use App\Logic\Membership\WorkAssignmentRefund\Model\CreditTransferTransaction;
use PHPUnit\Framework\TestCase;

final class Pain001FileWriterTest extends TestCase
{
    public function testWritesSepaCreditTransferInPain00100109(): void
    {
        $xpath = $this->write(new DirectDebitCreditor('Waldbad Borkheide e.V.', 'DE98ZZZ09999999999', 'DE02120300000000202051', 'BYLADEM1001'));

        self::assertSame('WB-AE-20261030100000', $this->value($xpath, '//p:GrpHdr/p:MsgId'));
        self::assertSame('2', $this->value($xpath, '//p:GrpHdr/p:NbOfTxs'));
        self::assertSame('35.00', $this->value($xpath, '//p:GrpHdr/p:CtrlSum'));
        self::assertSame('TRF', $this->value($xpath, '//p:PmtInf/p:PmtMtd'));
        self::assertSame('35.00', $this->value($xpath, '//p:PmtInf/p:CtrlSum'));
        self::assertSame('SEPA', $this->value($xpath, '//p:PmtTpInf/p:SvcLvl/p:Cd'));
        self::assertSame('2026-11-02', $this->value($xpath, '//p:ReqdExctnDt/p:Dt'));
        self::assertSame('Waldbad Borkheide e.V.', $this->value($xpath, '//p:Dbtr/p:Nm'));
        self::assertSame('DE02120300000000202051', $this->value($xpath, '//p:DbtrAcct/p:Id/p:IBAN'));
        self::assertSame('BYLADEM1001', $this->value($xpath, '//p:DbtrAgt/p:FinInstnId/p:BICFI'));
        self::assertSame('30.00', $this->value($xpath, "//p:CdtTrfTxInf[p:PmtId/p:EndToEndId='AE2026-Bad-01000-20261030']/p:Amt/p:InstdAmt"));
        self::assertSame('EUR', $this->value($xpath, "//p:CdtTrfTxInf[p:PmtId/p:EndToEndId='AE2026-Bad-01000-20261030']/p:Amt/p:InstdAmt/@Ccy"));
        // Umlaute und nicht zugelassene Zeichen werden auf den SEPA-Basiszeichensatz reduziert.
        self::assertSame('Joerg Mueller-Suess', $this->value($xpath, "//p:CdtTrfTxInf[p:PmtId/p:EndToEndId='AE2026-Bad-01000-20261030']/p:Cdtr/p:Nm"));
        self::assertSame('DE89370400440532013000', $this->value($xpath, "//p:CdtTrfTxInf[p:PmtId/p:EndToEndId='AE2026-Bad-01000-20261030']/p:CdtrAcct/p:Id/p:IBAN"));
        self::assertSame('Gutschrift Arbeitseinsatz 2026, Mitgl.-Nr. Bad-01000, 10 Std.', $this->value($xpath, "//p:CdtTrfTxInf[p:PmtId/p:EndToEndId='AE2026-Bad-01000-20261030']/p:RmtInf/p:Ustrd"));
    }

    public function testWithoutBicTheDebtorAgentIsNotProvided(): void
    {
        $xpath = $this->write(new DirectDebitCreditor('Waldbad Borkheide e.V.', null, 'DE02120300000000202051', null));

        self::assertSame('NOTPROVIDED', $this->value($xpath, '//p:DbtrAgt/p:FinInstnId/p:Othr/p:Id'));
        $bic = $xpath->query('//p:BICFI');
        self::assertNotFalse($bic);
        self::assertSame(0, $bic->length);
    }

    private function write(DirectDebitCreditor $debtor): \DOMXPath
    {
        $xml = (new Pain001FileWriter())->write(new CreditTransferBatch(
            messageId: 'WB-AE-20261030100000',
            createdAt: new \DateTimeImmutable('2026-10-30 10:00:00'),
            debtor: $debtor,
            executionDate: new \DateTimeImmutable('2026-11-02'),
            transactions: [
                new CreditTransferTransaction('AE2026-Bad-01000-20261030', 3000, 'Jörg Müller-Süß', 'DE89370400440532013000', 'Gutschrift Arbeitseinsatz 2026, Mitgl.-Nr. Bad-01000, 10 Std.'),
                new CreditTransferTransaction('AE2026-Bad-02000-20261030', 500, 'Erika Musterfrau', 'DE89370400440532013000', 'Gutschrift Arbeitseinsatz 2026, Mitgl.-Nr. Bad-02000, 5 Std.'),
            ],
        ));

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('p', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');

        return $xpath;
    }

    private function value(\DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);
        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, $expression);

        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMNode::class, $node);

        return $node->textContent;
    }
}
