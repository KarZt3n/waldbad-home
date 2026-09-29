<?php

namespace App\Tests\Integration\Data\Membership\DirectDebit\Writer;

use App\Data\Membership\DirectDebit\Writer\Pain008FileWriter;
use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use App\Logic\Membership\DirectDebit\Model\DirectDebitTransaction;
use App\Logic\Membership\DirectDebit\Model\SequenceType;
use PHPUnit\Framework\TestCase;

final class Pain008FileWriterTest extends TestCase
{
    public function testWritesCoreDirectDebitInPain00800108(): void
    {
        $xpath = $this->write(new DirectDebitCreditor('Waldbad Borkheide e.V.', 'DE98ZZZ09999999999', 'DE02120300000000202051', 'BYLADEM1001'));

        self::assertSame('WB-20260928100000-Bad-01000', $this->value($xpath, '//p:GrpHdr/p:MsgId'));
        self::assertSame('1', $this->value($xpath, '//p:GrpHdr/p:NbOfTxs'));
        self::assertSame('65.00', $this->value($xpath, '//p:GrpHdr/p:CtrlSum'));
        self::assertSame('CORE', $this->value($xpath, '//p:PmtTpInf/p:LclInstrm/p:Cd'));
        self::assertSame('RCUR', $this->value($xpath, '//p:PmtTpInf/p:SeqTp'));
        self::assertSame('2026-10-01', $this->value($xpath, '//p:ReqdColltnDt'));
        self::assertSame('DE02120300000000202051', $this->value($xpath, '//p:CdtrAcct/p:Id/p:IBAN'));
        self::assertSame('BYLADEM1001', $this->value($xpath, '//p:CdtrAgt/p:FinInstnId/p:BICFI'));
        self::assertSame('DE98ZZZ09999999999', $this->value($xpath, '//p:CdtrSchmeId//p:Othr/p:Id'));
        self::assertSame('65.00', $this->value($xpath, '//p:DrctDbtTxInf/p:InstdAmt'));
        self::assertSame('EUR', $this->value($xpath, '//p:DrctDbtTxInf/p:InstdAmt/@Ccy'));
        self::assertSame('MANDAT-1', $this->value($xpath, '//p:MndtRltdInf/p:MndtId'));
        self::assertSame('2020-03-01', $this->value($xpath, '//p:MndtRltdInf/p:DtOfSgntr'));
        self::assertSame('NOTPROVIDED', $this->value($xpath, '//p:DbtrAgt/p:FinInstnId/p:Othr/p:Id'));
        // Umlaute und nicht zugelassene Zeichen werden auf den SEPA-Basiszeichensatz reduziert.
        self::assertSame('Joerg Mueller-Suess', $this->value($xpath, '//p:Dbtr/p:Nm'));
        self::assertSame('Mitgliedsbeitrag 2026 Bad-01000 Mueller', $this->value($xpath, '//p:RmtInf/p:Ustrd'));
    }

    public function testWithoutBicTheCreditorAgentIsNotProvided(): void
    {
        $xpath = $this->write(new DirectDebitCreditor('Waldbad Borkheide e.V.', 'DE98ZZZ09999999999', 'DE02120300000000202051', null));

        self::assertSame('NOTPROVIDED', $this->value($xpath, '//p:CdtrAgt/p:FinInstnId/p:Othr/p:Id'));
        $bic = $xpath->query('//p:BICFI');
        self::assertNotFalse($bic);
        self::assertSame(0, $bic->length);
    }

    /**
     * Sammelexport: je Fälligkeit/Sequenz ein eigener Zahlungsblock, der Gruppenkopf summiert über
     * alle Blöcke.
     */
    public function testWritesOnePaymentInformationBlockPerBatch(): void
    {
        $xml = (new Pain008FileWriter())->write(
            $this->batch('WB-1-S1', '2027-03-01', SequenceType::Recurring, [$this->transaction('A', 6500), $this->transaction('B', 4000)]),
            $this->batch('WB-1-S2', '2027-03-15', SequenceType::First, [$this->transaction('C', 3000)]),
        );
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('p', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.08');

        self::assertSame('WB-1-S1', $this->value($xpath, '//p:GrpHdr/p:MsgId'));
        self::assertSame('3', $this->value($xpath, '//p:GrpHdr/p:NbOfTxs'));
        self::assertSame('135.00', $this->value($xpath, '//p:GrpHdr/p:CtrlSum'));
        self::assertSame('2', $this->value($xpath, "//p:PmtInf[p:PmtInfId='WB-1-S1']/p:NbOfTxs"));
        self::assertSame('105.00', $this->value($xpath, "//p:PmtInf[p:PmtInfId='WB-1-S1']/p:CtrlSum"));
        self::assertSame('RCUR', $this->value($xpath, "//p:PmtInf[p:PmtInfId='WB-1-S1']/p:PmtTpInf/p:SeqTp"));
        self::assertSame('2027-03-15', $this->value($xpath, "//p:PmtInf[p:PmtInfId='WB-1-S2']/p:ReqdColltnDt"));
        self::assertSame('FRST', $this->value($xpath, "//p:PmtInf[p:PmtInfId='WB-1-S2']/p:PmtTpInf/p:SeqTp"));
        self::assertSame('MANDAT-C', $this->value($xpath, "//p:PmtInf[p:PmtInfId='WB-1-S2']//p:MndtId"));
    }

    /**
     * @param list<DirectDebitTransaction> $transactions
     */
    private function batch(string $messageId, string $collectionDate, SequenceType $sequenceType, array $transactions): DirectDebitBatch
    {
        $creditor = new DirectDebitCreditor('Waldbad Borkheide e.V.', 'DE98ZZZ09999999999', 'DE02120300000000202051', null);

        return new DirectDebitBatch($messageId, new \DateTimeImmutable('2026-09-28 10:00:00'), $creditor, new \DateTimeImmutable($collectionDate), $sequenceType, $transactions);
    }

    private function transaction(string $id, int $cents): DirectDebitTransaction
    {
        return new DirectDebitTransaction($id, $cents, 'MANDAT-'.$id, new \DateTimeImmutable('2020-03-01'), 'Zahler '.$id, 'DE89370400440532013000', 'Beitrag');
    }

    private function write(DirectDebitCreditor $creditor): \DOMXPath
    {
        $xml = (new Pain008FileWriter())->write(new DirectDebitBatch(
            messageId: 'WB-20260928100000-Bad-01000',
            createdAt: new \DateTimeImmutable('2026-09-28 10:00:00'),
            creditor: $creditor,
            collectionDate: new \DateTimeImmutable('2026-10-01'),
            sequenceType: SequenceType::Recurring,
            transactions: [new DirectDebitTransaction(
                endToEndId: 'Bad-01000-20261001',
                amountCents: 6500,
                mandateReference: 'MANDAT-1',
                mandateSignedOn: new \DateTimeImmutable('2020-03-01'),
                debtorName: 'Jörg Müller-Süß',
                debtorIban: 'DE89370400440532013000',
                remittanceInformation: 'Mitgliedsbeitrag 2026 · Bad-01000 Müller',
            )],
        ));

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('p', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.08');

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
