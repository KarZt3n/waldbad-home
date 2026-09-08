<?php

namespace App\Logic\Membership\Member\Model;

enum PaymentMethod: string
{
    case SepaDirectDebit = 'sepa_direct_debit';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case NotSpecified = 'not_specified';
}
