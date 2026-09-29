<?php

namespace App\Data\Membership\DirectDebit\Writer;

use App\Logic\Membership\DirectDebit\DirectDebitFileWriterInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;

/**
 * SEPA-Basislastschrift (CORE) im Format pain.008.001.08, wie es deutsche Banken laut
 * DFÜ-Abkommen Anlage 3 aktuell erwarten. Ohne BIC wird wie dort vorgesehen „NOTPROVIDED“
 * eingetragen (IBAN-only).
 *
 * Texte werden auf den SEPA-Basiszeichensatz reduziert (Umlaute umschreiben, übrige Sonderzeichen
 * entfernen), da nicht jede Bank den erweiterten deutschen Zeichensatz annimmt.
 */
readonly class Pain008FileWriter implements DirectDebitFileWriterInterface
{
    private const string NAMESPACE = 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.08';

    public function write(DirectDebitBatch $batch, DirectDebitBatch ...$furtherBatches): string
    {
        $batches = [$batch, ...$furtherBatches];
        $creditor = $batch->creditor;
        if ($creditor->name === null || $creditor->creditorId === null || $creditor->iban === null) {
            throw new \LogicException('DirectDebitBatch garantiert vollständige Gläubigerdaten.');
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS(self::NAMESPACE, 'Document');
        $document->appendChild($root);
        $initiation = $this->append($document, $root, 'CstmrDrctDbtInitn');

        $header = $this->append($document, $initiation, 'GrpHdr');
        $this->append($document, $header, 'MsgId', $this->text($batch->messageId, 35));
        $this->append($document, $header, 'CreDtTm', $batch->createdAt->format('Y-m-d\TH:i:s'));
        $this->append($document, $header, 'NbOfTxs', (string) array_sum(array_map(static fn (DirectDebitBatch $each): int => count($each->transactions), $batches)));
        $this->append($document, $header, 'CtrlSum', $this->amount(array_sum(array_map(static fn (DirectDebitBatch $each): int => $each->totalCents(), $batches))));
        $this->append($document, $this->append($document, $header, 'InitgPty'), 'Nm', $this->text($creditor->name, 70));

        foreach ($batches as $each) {
            $this->appendPaymentInformation($document, $initiation, $each, $creditor->name, $creditor->iban, $creditor->creditorId, $creditor->bic);
        }

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Die SEPA-Lastschriftdatei konnte nicht erzeugt werden.');
        }

        return $xml;
    }

    private function appendPaymentInformation(\DOMDocument $document, \DOMElement $initiation, DirectDebitBatch $batch, string $creditorName, string $creditorIban, string $creditorId, ?string $creditorBic): void
    {
        $count = (string) count($batch->transactions);
        $controlSum = $this->amount($batch->totalCents());
        $payment = $this->append($document, $initiation, 'PmtInf');
        $this->append($document, $payment, 'PmtInfId', $this->text($batch->messageId, 35));
        $this->append($document, $payment, 'PmtMtd', 'DD');
        $this->append($document, $payment, 'NbOfTxs', $count);
        $this->append($document, $payment, 'CtrlSum', $controlSum);
        $paymentType = $this->append($document, $payment, 'PmtTpInf');
        $this->append($document, $this->append($document, $paymentType, 'SvcLvl'), 'Cd', 'SEPA');
        $this->append($document, $this->append($document, $paymentType, 'LclInstrm'), 'Cd', 'CORE');
        $this->append($document, $paymentType, 'SeqTp', $batch->sequenceType->value);
        $this->append($document, $payment, 'ReqdColltnDt', $batch->collectionDate->format('Y-m-d'));
        $this->append($document, $this->append($document, $payment, 'Cdtr'), 'Nm', $this->text($creditorName, 70));
        $this->append($document, $this->append($document, $this->append($document, $payment, 'CdtrAcct'), 'Id'), 'IBAN', $creditorIban);
        $this->appendAgent($document, $this->append($document, $payment, 'CdtrAgt'), $creditorBic);
        $this->append($document, $payment, 'ChrgBr', 'SLEV');
        $schemeOther = $this->append($document, $this->append($document, $this->append($document, $this->append($document, $payment, 'CdtrSchmeId'), 'Id'), 'PrvtId'), 'Othr');
        $this->append($document, $schemeOther, 'Id', $creditorId);
        $this->append($document, $this->append($document, $schemeOther, 'SchmeNm'), 'Prtry', 'SEPA');

        foreach ($batch->transactions as $transaction) {
            $entry = $this->append($document, $payment, 'DrctDbtTxInf');
            $this->append($document, $this->append($document, $entry, 'PmtId'), 'EndToEndId', $this->text($transaction->endToEndId, 35));
            $this->append($document, $entry, 'InstdAmt', $this->amount($transaction->amountCents))->setAttribute('Ccy', 'EUR');
            $mandate = $this->append($document, $this->append($document, $entry, 'DrctDbtTx'), 'MndtRltdInf');
            $this->append($document, $mandate, 'MndtId', $this->text($transaction->mandateReference, 35));
            $this->append($document, $mandate, 'DtOfSgntr', $transaction->mandateSignedOn->format('Y-m-d'));
            $this->appendAgent($document, $this->append($document, $entry, 'DbtrAgt'), null);
            $this->append($document, $this->append($document, $entry, 'Dbtr'), 'Nm', $this->text($transaction->debtorName, 70));
            $this->append($document, $this->append($document, $this->append($document, $entry, 'DbtrAcct'), 'Id'), 'IBAN', $transaction->debtorIban);
            $this->append($document, $this->append($document, $entry, 'RmtInf'), 'Ustrd', $this->text($transaction->remittanceInformation, 140));
        }
    }

    private function appendAgent(\DOMDocument $document, \DOMElement $agent, ?string $bic): void
    {
        $institution = $this->append($document, $agent, 'FinInstnId');
        if ($bic !== null) {
            $this->append($document, $institution, 'BICFI', $bic);

            return;
        }
        $this->append($document, $this->append($document, $institution, 'Othr'), 'Id', 'NOTPROVIDED');
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

    private function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function text(string $value, int $maxLength): string
    {
        $transliterated = strtr($value, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
            'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a', 'ó' => 'o', 'ò' => 'o', 'ç' => 'c', 'ñ' => 'n',
            '&' => '+', '–' => '-', '—' => '-', '·' => ' ',
        ]);
        $allowed = (string) preg_replace("/[^A-Za-z0-9\\/\\-?:().,'+ ]/", '', $transliterated);

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $allowed)), 0, $maxLength);
    }
}
