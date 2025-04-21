<?php

namespace Unicodeveloper\Paystack;

class Endpoints {
    public const TRANSFER = '/transfer';
    public const TRANSFER_RECIPIENT = '/transferrecipient';
    public const TRANSFER_FINALIZE = '/transfer/finalize_transfer';
    public const TRANSACTION = '/transaction';
    public const TRANSACTION_INITIALIZE = '/transaction/initialize';
    public const TRANSACTION_VERIFY = '/transaction/verify';
    public const TRANSACTION_EXPORT = '/transaction/export';
    public const SUBSCRIPTION = '/subscription';
    public const SUBSCRIPTION_ENABLE= '/subscription/enable';
    public const SUBSCRIPTION_DISABLE= '/subscription/disable';
    public const PAGE = '/page';
    public const SUBACCOUNT = '/subaccount';
    public const BANK = '/bank';
    public const PLAN = '/plan';
    public const CUSTOMER = '/customer';

    private function __construct() {}
}
