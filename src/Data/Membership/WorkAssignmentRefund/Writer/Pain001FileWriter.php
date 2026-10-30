<?php

namespace App\Data\Membership\WorkAssignmentRefund\Writer;

use App\Data\Common\Sepa\SepaText;
use App\Logic\Membership\WorkAssignmentRefund\CreditTransferFileWriterInterface;
use App\Logic\Membership\WorkAssignmentRefund\Model\CreditTransferBatch;

/**
 * SEPA-Überweisung (Sammler) im Format pain.001.001.09, wie es deutsche Banken laut DFÜ-Abkommen
 * Anlage 3 aktuell erwarten. Ohne BIC des Vereinskontos wird „NOTPROVIDED“ eingetragen (IBAN-only),
 * die Bank des Empfängers wird gar nicht angegeben. Texte werden über `SepaText` auf den
 * SEPA-Basiszeichensatz reduziert.
 */
readonly class Pain001FileWriter implements CreditTransferFileWriterInterface
{
    private const string NAMESPACE = 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09';

    public function write(CreditTransferBatch $batch): string
    {
        $debtor = $batch->debtor;
        if ($debtor->name === null || $debtor->iban === null) {
            throw new \LogicException('CreditTransferBatch garantiert Name und IBAN des Vereinskontos.');
        }
        $count = (string) count($batch->transactions);
        $controlSum = SepaText::amount($batch->totalCents());

        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS(self::NAMESPACE, 'Document');
        $document->appendChild($root);
        $initiation = $this->append($document, $root, 'CstmrCdtTrfInitn');

        $header = $this->append($document, $initiation, 'GrpHdr');
        $this->append($document, $header, 'MsgId', SepaText::text($batch->messageId, 35));
        $this->append($document, $header, 'CreDtTm', $batch->createdAt->format('Y-m-d\TH:i:s'));
        $this->append($document, $header, 'NbOfTxs', $count);
        $this->append($document, $header, 'CtrlSum', $controlSum);
        $this->append($document, $this->append($document, $header, 'InitgPty'), 'Nm', SepaText::text($debtor->name, 70));

        $payment = $this->append($document, $initiation, 'PmtInf');
        $this->append($document, $payment, 'PmtInfId', SepaText::text($batch->messageId, 35));
        $this->append($document, $payment, 'PmtMtd', 'TRF');
        $this->append($document, $payment, 'NbOfTxs', $count);
        $this->append($document, $payment, 'CtrlSum', $controlSum);
        $this->append($document, $this->append($document, $this->append($document, $payment, 'PmtTpInf'), 'SvcLvl'), 'Cd', 'SEPA');
        $this->append($document, $this->append($document, $payment, 'ReqdExctnDt'), 'Dt', $batch->executionDate->format('Y-m-d'));
        $this->append($document, $this->append($document, $payment, 'Dbtr'), 'Nm', SepaText::text($debtor->name, 70));
        $this->append($document, $this->append($document, $this->append($document, $payment, 'DbtrAcct'), 'Id'), 'IBAN', $debtor->iban);
        $institution = $this->append($document, $this->append($document, $payment, 'DbtrAgt'), 'FinInstnId');
        if ($debtor->bic !== null) {
            $this->append($document, $institution, 'BICFI', $debtor->bic);
        } else {
            $this->append($document, $this->append($document, $institution, 'Othr'), 'Id', 'NOTPROVIDED');
        }
        $this->append($document, $payment, 'ChrgBr', 'SLEV');

        foreach ($batch->transactions as $transaction) {
            $entry = $this->append($document, $payment, 'CdtTrfTxInf');
            $this->append($document, $this->append($document, $entry, 'PmtId'), 'EndToEndId', SepaText::text($transaction->endToEndId, 35));
            $this->append($document, $this->append($document, $entry, 'Amt'), 'InstdAmt', SepaText::amount($transaction->amountCents))->setAttribute('Ccy', 'EUR');
            $this->append($document, $this->append($document, $entry, 'Cdtr'), 'Nm', SepaText::text($transaction->creditorName, 70));
            $this->append($document, $this->append($document, $this->append($document, $entry, 'CdtrAcct'), 'Id'), 'IBAN', $transaction->creditorIban);
            $this->append($document, $this->append($document, $entry, 'RmtInf'), 'Ustrd', SepaText::text($transaction->remittanceInformation, 140));
        }

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Die SEPA-Überweisungsdatei konnte nicht erzeugt werden.');
        }

        return $xml;
    }

    private function append(\DOMDocument $document, \DOMElement $parent, string $name, ?string $value = null): \DOMElement
    {
        $element = $document->createElementNS(self::NAMESPACE, $name);
        if ($value !== null) {
            $element->appendChild($document->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }
}
