<?php

/*
 * This file is part of the Laravel Paystack package.
 *
 * (c) Prosper Otemuyiwa <prosperotemuyiwa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Unicodeveloper\Paystack\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static Paystack makePaymentRequest(?array $data = null)
 * @method static Paystack getAuthorizationUrl(?array $data = null)
 * @method static array getAuthorizationResponse(array $data)
 * @method static array getAuthorizationResponse(?array $data = null)
 * @method static bool isTransactionVerificationValid(?string $transaction_id = null)
 * @method static array getPaymentData()
 * @method static \Illuminate\Http\RedirectResponse redirectNow()
 * @method static string getAccessCode()
 * @method static string genTranxRef()
 * @method static array getAllCustomers()
 * @method static array getAllPlans()
 * @method static array getAllTransactions()
 * @method static array getResponse()
 * @method static array getData()
 * @method static array createPlan()
 * @method static array fetchPlan(string $plan_code)
 * @method static array updatePlan(string $plan_code)
 * @method static array createCustomer(?array $data = null)
 * @method static array fetchCustomer(string|int $customer_id)
 * @method static array updateCustomer(string|int $customer_id)
 * @method static array exportTransactions()
 * @method static array createSubscription()
 * @method static array getAllSubscriptions()
 * @method static array getCustomerSubscriptions(string|int $customer_id)
 * @method static array getPlanSubscriptions(string|int $plan_id)
 * @method static array enableSubscription()
 * @method static array disableSubscription()
 * @method static array fetchSubscription(string|int $subscription_id)
 * @method static array createPage()
 * @method static array getAllPages()
 * @method static array fetchPage(string|int $page_id)
 * @method static array updatePage(string|int $page_id)
 * @method static array createSubAccount()
 * @method static array fetchSubAccount(string $subaccount_code)
 * @method static array listSubAccounts(int $per_page, int $page)
 * @method static array updateSubAccount(array $subaccount_code)
 * @method static array getBanks(?string $country, int $per_page = 50, bool $use_cursor = false)
 * @method static array confirmAccount(string $account_number, string $bank_code)
 * @method static array createTransferRecipient(?array $data = null)
 * @method static array getTransferRecipients()
 * @method static array retrieveTransfer()
 * @method static array finalizeTransfer(?array $data = null)
 * @method static array verifyTransfer(string $reference)
 * @method static array makeTransfer(?array $data = null)
 */
class Paystack extends Facade
{
    /**
     * Get the registered name of the component
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-paystack';
    }
}
