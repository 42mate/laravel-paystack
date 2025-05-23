<?php

/*
 * This file is part of the Laravel Paystack package.
 *
 * (c) Prosper Otemuyiwa <prosperotemuyiwa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Unicodeveloper\Paystack;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Unicodeveloper\Paystack\Exceptions\IsNullException;
use Unicodeveloper\Paystack\Exceptions\PaymentVerificationFailedException;

class Paystack
{
    /**
     * Transaction Verification Successful
     */
    const VS = "Verification successful";

    /**
     *  Invalid Transaction reference
     */
    const ITF = "Invalid transaction reference";

    /**
     * Issue Secret Key from your Paystack Dashboard
     * @var string
     */
    protected $secretKey;

    /**
     * Instance of Client
     * @var Client
     */
    protected $client;

    /**
     *  Response from requests made to Paystack
     * @var mixed
     */
    protected $response;

    /**
     * Paystack API base Url
     * @var string
     */
    protected $baseUrl;

    /**
     * Authorization Url - Paystack payment page
     * @var string
     */
    protected $authorizationUrl;

    public function __construct()
    {
        $this->setKey();
        $this->setBaseUrl();
        $this->setRequestOptions();
    }

    /**
     * Get Base Url from Paystack config file
     */
    public function setBaseUrl(): void
    {
        $this->baseUrl = Config::get("paystack.paymentUrl");
    }

    /**
     * Get secret key from Paystack config file
     */
    public function setKey(): void
    {
        $this->secretKey = Config::get("paystack.secretKey");
    }

    /**
     * Set options for making the Client request
     */
    private function setRequestOptions(): void
    {
        $authBearer = "Bearer " . $this->secretKey;

        $this->client = new Client([
            "base_uri" => $this->baseUrl,
            "headers" => [
                "Authorization" => $authBearer,
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ],
        ]);
    }

    /**

     * Initiate a payment request to Paystack
     * Included the option to pass the payload to this method for situations
     * when the payload is built on the fly (not passed to the controller from a view)
     * @return Paystack
     */
    public function makePaymentRequest(?array $data = null): Paystack
    {
        if ($data == null) {
            $quantity = intval(request()->quantity ?? 1);

            $data = array_filter([
                "amount" => intval(request()->amount) * $quantity,
                "reference" => request()->reference,
                "email" => request()->email,
                "channels" => request()->channels,
                "plan" => request()->plan,
                "first_name" => request()->first_name,
                "last_name" => request()->last_name,
                "callback_url" => request()->callback_url,
                "currency" =>
                    request()->currency != "" ? request()->currency : "NGN",

                /*
                    Paystack allows for transactions to be split into a subaccount -
                    The following lines trap the subaccount ID - as well as the ammount to charge the subaccount (if overriden in the form)
                    both values need to be entered within hidden input fields
                */
                "subaccount" => request()->subaccount,
                "transaction_charge" => request()->transaction_charge,

                /**
                 * Paystack allows for transaction to be split into multi accounts(subaccounts)
                 * The following lines trap the split ID handling the split
                 * More details here: https://paystack.com/docs/payments/multi-split-payments/#using-transaction-splits-with-payments
                 */
                "split_code" => request()->split_code,

                /**
                 * Paystack allows transaction to be split into multi account(subaccounts) on the fly without predefined split
                 * form need an input field: <input type="hidden" name="split" value="{{ json_encode($split) }}" >
                 * array must be set up as:
                 *  $split = [
                 *    "type" => "percentage",
                 *     "currency" => "KES",
                 *     "subaccounts" => [
                 *       { "subaccount" => "ACCT_li4p6kte2dolodo", "share" => 10 },
                 *       { "subaccount" => "ACCT_li4p6kte2dolodo", "share" => 30 },
                 *     ],
                 *     "bearer_type" => "all",
                 *     "main_account_share" => 70,
                 * ]
                 * More details here: https://paystack.com/docs/payments/multi-split-payments/#dynamic-splits
                 */
                "split" => request()->split,
                /*
                 * to allow use of metadata on Paystack dashboard and a means to return additional data back to redirect url
                 * form need an input field: <input type="hidden" name="metadata" value="{{ json_encode($array) }}" >
                 * array must be set up as:
                 * $array = [ 'custom_fields' => [
                 *                   ['display_name' => "Cart Id", "variable_name" => "cart_id", "value" => "2"],
                 *                   ['display_name' => "Sex", "variable_name" => "sex", "value" => "female"],
                 *                   .
                 *                   .
                 *                   .
                 *                  ]
                 *          ]
                 */
                "metadata" => request()->metadata,
            ]);
        }

        $this->setHttpResponse(Endpoints::TRANSACTION_INITIALIZE, "POST", $data);

        return $this;
    }

    /**
     * @param string $relativeUrl
     * @param string $method
     * @param array $body
     * @return Paystack
     * @throws IsNullException
     */
    private function setHttpResponse(
        $relativeUrl,
        $method,
        $body = []
    ): Paystack {
        if (is_null($method)) {
            throw new IsNullException("Empty method not allowed");
        }

        $this->response = $this->client->{strtolower($method)}(
            $this->baseUrl . $relativeUrl,
            ["body" => json_encode($body)]
        );

        return $this;
    }

    /**
     * Get the authorization url from the callback response
     * @return Paystack
     */
    public function getAuthorizationUrl($data = null)
    {
        $this->makePaymentRequest($data);

        $this->url = $this->getResponse()["data"]["authorization_url"];

        return $this;
    }

    /**
     * Get the authorization callback response
     * In situations where Laravel serves as an backend for a detached UI, the api cannot redirect
     * and might need to take different actions based on the success or not of the transaction
     * @return array
     */
    public function getAuthorizationResponse($data)
    {
        $this->makePaymentRequest($data);

        $this->url = $this->getResponse()["data"]["authorization_url"];

        return $this->getResponse();
    }

    /**
     * Hit Paystack Gateway to Verify that the transaction is valid
     */
    private function verifyTransactionAtGateway($transaction_id = null)
    {
        $transactionRef = $transaction_id ?? request()->query("trxref");
        $endpoint = Endpoints::TRANSACTION_VERIFY;
        $relativeUrl = "{$endpoint}/{$transactionRef}";

        $this->response = $this->client->get($this->baseUrl . $relativeUrl, []);
    }

    /**
     * True or false condition whether the transaction is verified
     * @return boolean
     */
    public function isTransactionVerificationValid($transactionId = null)
    {
        $this->verifyTransactionAtGateway($transactionId);

        $result = $this->getResponse()["message"];

        switch ($result) {
            case self::VS:
                $validate = true;
                break;
            case self::ITF:
                $validate = false;
                break;
            default:
                $validate = false;
                break;
        }

        return $validate;
    }

    /**
     * Get Payment details if the transaction was verified successfully
     * @return json
     * @throws PaymentVerificationFailedException
     */
    public function getPaymentData()
    {
        if ($this->isTransactionVerificationValid()) {
            return $this->getResponse();
        } else {
            throw new PaymentVerificationFailedException(
                "Invalid Transaction Reference"
            );
        }
    }

    /**
     * Fluent method to redirect to Paystack Payment Page
     */
    public function redirectNow()
    {
        return redirect($this->url);
    }

    /**
     * Get Access code from transaction callback respose
     * @return string
     */
    public function getAccessCode()
    {
        return $this->getResponse()["data"]["access_code"];
    }

    /**
     * Generate a Unique Transaction Reference
     * @return string
     */
    public function genTranxRef()
    {
        return TransRef::getHashedToken();
    }

    /**
     * Get all the customers that have made transactions on your platform
     * @return array
     */
    public function getAllCustomers()
    {
        $this->setRequestOptions();

        return $this->setHttpResponse(Endpoints::CUSTOMER, "GET", [])->getData();
    }

    /**
     * Get all the plans that you have on Paystack
     * @return array
     */
    public function getAllPlans()
    {
        $this->setRequestOptions();

        return $this->setHttpResponse(Endpoints::PLAN, "GET", [])->getData();
    }

    /**
     * Get all the transactions that have happened overtime
     * @return array
     */
    public function getAllTransactions()
    {
        $this->setRequestOptions();

        return $this->setHttpResponse(Endpoints::TRANSACTION, "GET", [])->getData();
    }

    /**
     * Get the whole response from a get operation
     * @return array
     */
    public function getResponse()
    {
        return json_decode($this->response->getBody(), true);
    }

    /**
     * Get the data response from a get operation
     * @return array
     */
    public function getData()
    {
        return $this->getResponse()["data"];
    }

    /**
     * Create a plan
     */
    public function createPlan(?array $data = null): array
    {
        if (is_null($data)) {
            $data = [
                "name" => request()->name,
                "description" => request()->desc,
                "amount" => intval(request()->amount),
                "interval" => request()->interval,
                "send_invoices" => request()->send_invoices,
                "send_sms" => request()->send_sms,
                "currency" => request()->currency,
            ];
        }

        $this->setRequestOptions();

        return $this->setHttpResponse(Endpoints::PLAN, "POST", $data)->getResponse();
    }

    /**
     * Fetch any plan based on its plan id or code
     * @param $plan_code
     * @return array
     */
    public function fetchPlan($planCode)
    {
        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::PLAN . "/" . $planCode,
            "GET",
            []
        )->getResponse();
    }

    /**
     * Update any plan's details based on its id or code
     * @param $plan_code
     * @return array
     */
    public function updatePlan(string $planCode, ?array $data = null)
    {

        if (is_null($data)) {
            $data = [
                "name" => request()->name,
                "description" => request()->desc,
                "amount" => intval(request()->amount),
                "interval" => request()->interval,
                "send_invoices" => request()->send_invoices,
                "send_sms" => request()->send_sms,
                "currency" => request()->currency,
            ];
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::PLAN . "/" . $planCode,
            "PUT",
            $data
        )->getResponse();
    }

    /**
     * Create a customer
     */
    public function createCustomer($data = null)
    {
        if ($data == null) {
            $data = [
                "email" => request()->email,
                "first_name" => request()->fname,
                "last_name" => request()->lname,
                "phone" => request()->phone,
                "metadata" => request()
                    ->additional_info /* key => value pairs array */,
            ];
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::CUSTOMER,
            "POST",
            $data
        )->getResponse();
    }

    /**
     * Fetch a customer based on id or code
     * @param $customer_id
     * @return array
     */
    public function fetchCustomer($customerId)
    {
        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::CUSTOMER . "/" . $customerId,
            "GET",
            []
        )->getResponse();
    }

    /**
     * Update a customer's details based on their id or code
     * @param $customer_id
     * @return array
     */
    public function updateCustomer(string $customerId, ?array $data = null): array
    {
        if (is_null($data)) {
            $data = [
                "email" => request()->email,
                "first_name" => request()->fname,
                "last_name" => request()->lname,
                "phone" => request()->phone,
                "metadata" => request()
                    ->additional_info /* key => value pairs array */,
            ];
        }
        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::CUSTOMER . "/" . $customerId,
            "PUT",
            $data
        )->getResponse();
    }

    /**
     * Export transactions in .CSV
     * @return array
     */
    public function exportTransactions()
    {
        $data = [
            "from" => request()->from,
            "to" => request()->to,
            "settled" => request()->settled,
        ];

        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::TRANSACTION_EXPORT,
            "GET",
            $data
        )->getResponse();
    }

    /**
     * Create a subscription to a plan from a customer.
     */
    public function createSubscription(?array $data = null): array
    {
        if (is_null($data)) {
            $data = [
                "customer" => request()->customer, //Customer email or code
                "plan" => request()->plan,
                "authorization" => request()->authorization_code,
            ];
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::SUBSCRIPTION,
            "POST",
            $data
        )->getResponse();
    }

    /**
     * Get all the subscriptions made on Paystack.
     *
     * @return array
     */
    public function getAllSubscriptions()
    {
        $this->setRequestOptions();
        return $this->setHttpResponse("/subscription", "GET", [])->getData();
    }

    /**
     * Get customer subscriptions
     *
     * @param integer $customer_id
     * @return array
     */
    public function getCustomerSubscriptions($customerId)
    {
        $this->setRequestOptions();

        return $this->setHttpResponse(
            Endpoints::SUBSCRIPTION . "?customer=" . $customerId,
            "GET",
            []
        )->getData();
    }

    /**
     * Get plan subscriptions
     *
     * @param  integer $plan_id
     * @return array
     */
    public function getPlanSubscriptions($planId)
    {
        $this->setRequestOptions();

        return $this->setHttpResponse(
            Endpoints::SUBSCRIPTION . "?plan=" . $planId,
            "GET",
            []
        )->getData();
    }

    /**
     * Enable a subscription using the subscription code and token
     * @return array
     */
    public function enableSubscription(?array $data = null): array
    {
        if (is_null($data)) {
            $data = [
                "code" => request()->code,
                "token" => request()->token,
            ];
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::SUBSCRIPTION_ENABLE,
            "POST",
            $data
        )->getResponse();
    }

    /**
     * Disable a subscription using the subscription code and token
     * @return array
     */
    public function disableSubscription(?array $data = null): array
    {
        if (is_null($data)) {
            $data = [
                "code" => request()->code,
                "token" => request()->token,
            ];
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::SUBSCRIPTION_DISABLE,
            "POST",
            $data
        )->getResponse();
    }

    /**
     * Fetch details about a certain subscription
     * @param mixed $subscriptionId
     * @return array
     */
    public function fetchSubscription($subscriptionId)
    {
        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::SUBSCRIPTION . "/" . $subscriptionId,
            "GET",
            []
        )->getResponse();
    }

    /**
     * Create pages you can share with users using the returned slug
     */
    public function createPage(?array $data = null): array
    {
        if (is_null($data)) {
            $data = [
                "name" => request()->name,
                "description" => request()->description,
                "amount" => request()->amount,
            ];
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(Endpoints::PAGE, "POST", $data)->getResponse();
    }

    /**
     * Fetches all the pages the merchant has
     * @return array
     */
    public function getAllPages()
    {
        $this->setRequestOptions();
        return $this->setHttpResponse(Endpoints::PAGE, "GET", [])->getResponse();
    }

    /**
     * Fetch details about a certain page using its id or slug
     * @param mixed $page_id
     * @return array
     */
    public function fetchPage($pageId)
    {
        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::PAGE . '/' . $pageId,
            "GET",
            []
        )->getResponse();
    }

    /**
     * Update the details about a particular page
     * @param $page_id
     * @return array
     */
    public function updatePage(mixed $pageId, ?array $data = null): array
    {
        if (is_null($array)) {
            $data = [
                "name" => request()->name,
                "description" => request()->description,
                "amount" => request()->amount,
            ];
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::PAGE . '/' . $pageId,
            "PUT",
            $data
        )->getResponse();
    }

    /**
     * Creates a subaccount to be used for split payments . Required    params are business_name , settlement_bank , account_number ,   percentage_charge
     *
     * @return array
     */
    public function createSubAccount(?array $data = null): array
    {
        if (is_null($data)) {
            $data = [
                "business_name" => request()->business_name,
                "settlement_bank" => request()->settlement_bank,
                "account_number" => request()->account_number,
                "percentage_charge" => request()->percentage_charge,
                "primary_contact_email" => request()->primary_contact_email,
                "primary_contact_name" => request()->primary_contact_name,
                "primary_contact_phone" => request()->primary_contact_phone,
                "metadata" => request()->metadata,
                "settlement_schedule" => request()->settlement_schedule,
            ];
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(
            "/subaccount",
            "POST",
            array_filter($data)
        )->getResponse();
    }

    /**
     * Fetches details of a subaccount
     * @param subaccount code
     * @return array
     */
    public function fetchSubAccount($subaccountCode): array
    {
        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::SUBACCOUNT . '/' . $subaccountCode,
            "GET",
            []
        )->getResponse();
    }

    /**
     * Lists all the subaccounts associated with the account
     * @param $per_page - Specifies how many records to retrieve per page , $page - SPecifies exactly what page to retrieve
     * @return array
     */
    public function listSubAccounts($perPage, $page): array
    {
        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::SUBACCOUNT . '/?perPage=' . (int) $perPage . "&page=" . (int) $page,
            "GET"
        )->getResponse();
    }

    /**
     * Updates a subaccount to be used for split payments . Required params are business_name , settlement_bank , account_number , percentage_charge
     * @param subaccount code
     * @return array
     */
    public function updateSubAccount(array $subaccountCode, ?array $data = null): array
    {
        if (is_null($data)) {
            $data = [
                "business_name" => request()->business_name,
                "settlement_bank" => request()->settlement_bank,
                "account_number" => request()->account_number,
                "percentage_charge" => request()->percentage_charge,
                "description" => request()->description,
                "primary_contact_email" => request()->primary_contact_email,
                "primary_contact_name" => request()->primary_contact_name,
                "primary_contact_phone" => request()->primary_contact_phone,
                "metadata" => request()->metadata,
                "settlement_schedule" => request()->settlement_schedule,
            ];
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::SUBACCOUNT ."/{$subaccountCode}",
            "PUT",
            array_filter($data)
        )->getResponse();
    }

    /**
     * Get a list of all supported banks and their properties
     * @param $country - The country from which to obtain the list of supported banks, $per_page - Specifies how many records to retrieve per page ,
     * $use_cursor - Flag to enable cursor pagination on the endpoint
     * @return array
     */
    public function getBanks(
        ?string $country,
        int $per_page = 50,
        bool $use_cursor = false
    ) {
        if (!$country) {
            $country = request()->country ?? "nigeria";
        }

        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::BANK . "/?country=" .
                $country .
                "&use_cursor=" .
                $use_cursor .
                "&perPage=" .
                (int) $per_page,
            "GET"
        )->getResponse();
    }

    /**
     * Confirm an account belongs to the right customer
     * @param $account_number - Account Number, $bank_code - You can get the list of bank codes by calling the List Banks endpoint
     * @return array
     */
    public function confirmAccount(string $accountNumber, string $bankCode)
    {
        $this->setRequestOptions();
        return $this->setHttpResponse(
            Endpoints::BANK . "/resolve/?account_number=" .
                $accountNumber .
                "&bank_code=" .
                $bankCode,
            "GET"
        )->getResponse();
    }

    /**
     * Create a transfer recipient.
     *
     * If no data is provided, it uses the current request to populate the fields.
     *
     * Required fields:
     * - type
     * - name
     * - account_number
     * - bank_code
     *
     * Optional fields:
     * - description
     * - currency
     * - authorization_code
     * - metadata
     *
     * @param array|null $data Optional data to create the transfer recipient.
     * @return array The API response.
     */
    public function createTransferRecipient(?array $data = null): array
    {
        if ($data === null) {
            $data = [
                "type" => request()->type,
                "name" => request()->name,
                "account_number" => request()->account_number,
                "bank_code" => request()->bank_code,
            ];

            foreach (["description", "currency", "authorization_code", "metadata"] as $optional) {
                if (request()->has($optional)) {
                    $data[$optional] = request()->$optional;
                }
            }
        }

        return $this
            ->setHttpResponse(Endpoints::TRANSFER_RECIPIENT, "POST", $data)
            ->getResponse();
    }

    /**
     * Retrieve details of a transfer recipient.
     *
     * This method sends a GET request to fetch information about a specific 
     * transfer recipient using their recipient code.
     *
     * @param string $recipientCode The unique code identifying the transfer recipient.
     *
     * @return array The response data containing the recipient's details.
     */
    public function retrieveTransferRecipient(string $recipientCode): array
    {
        $this->setHttpResponse(Endpoints::TRANSFER_RECIPIENT . '/' . $recipientCode, 'GET');
        return $this->getResponse();
    }

    /**
     * Retrieve all transfer recipients.
     *
     * @return array The API response containing transfer recipients.
     */
    public function getTransferRecipients(): array
    {
        $this->setHttpResponse(Endpoints::TRANSFER_RECIPIENT, 'GET');
        return $this->getResponse();
    }

    /**
     * Retrieve details of a transfer.
     *
     * @return array The API response containing transfer details.
     */
    public function retrieveTransfer(): array
    {
        return $this
            ->setHttpResponse(Endpoints::TRANSFER, 'GET')
            ->getResponse();
    }

    /**
     * Finalize a transfer that requires OTP validation.
     *
     * If no data is provided, it uses the current request to populate the fields.
     *
     * Required fields:
     * - transfer_code
     * - otp
     *
     * @param array|null $data Optional data to finalize the transfer.
     * @return array The API response.
     */
    public function finalizeTransfer(?array $data = null): array
    {
        if (is_null($data)) {
            $data = [
                'transfer_code' => request()->transfer_code,
                'otp' => request()->otp,
            ];
        }

        return $this
            ->setHttpResponse(Endpoints::TRANSFER . '/finalize_transfer', 'POST', $data)
            ->getResponse();
    }

    /**
     * Verify the status of a transfer by its reference.
     *
     * @param string $reference The transfer reference to verify.
     * @return array The API response.
     */
    public function verifyTransfer(string $reference): array
    {
        return $this
            ->setHttpResponse(Endpoints::TRANSFER_FINALIZE . '/' . $reference, 'GET')
            ->getResponse();
    }

    /**
     * Initiate a new transfer.
     *
     * If no data is provided, it uses the current request to populate the fields.
     *
     * Required fields:
     * - source (e.g., "balance")
     * - reason
     * - amount
     * - recipient
     *
     * Example payload:
     * {
     *   "source": "balance",
     *   "reason": "Calm down",
     *   "amount": 3794800,
     *   "recipient": "RCP_gx2wn530m0i3w3m"
     * }
     *
     * @param array|null $data Optional data to initiate the transfer.
     * @return array The API response.
     */
    public function makeTransfer(?array $data = null): array
    {
        if ($data === null) {
            $data = [
                "source" => request()->source,
                "reason" => request()->reason,
                "amount" => request()->amount,
                "recipient" => request()->recipient,
            ];
        }
        return $this
            ->setHttpResponse(Endpoints::TRANSFER, "POST", $data)
            ->getResponse();
    }
}
