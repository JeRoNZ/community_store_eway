<?php
namespace Concrete\Package\CommunityStoreEway\Src\CommunityStore\Payment\Methods\CommunityStoreEway;

/*
 * Author: Jeremy Rogers infoatjero.co.nz
 * License: MIT
 */

use Core;
use URL;
use Config;
use Session;
use Log;

use \Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Order\Order as StoreOrder;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Customer\Customer as StoreCustomer;

require(DIR_PACKAGES . DIRECTORY_SEPARATOR . 'community_store_eway' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');


class CommunityStoreEwayPaymentMethod extends StorePaymentMethod {

	public function redirectForm () {
		$client = $this->makeClient();

		$oid = Session::get('orderID');
		$order = StoreOrder::getByID($oid);
		if (!$order) {
			throw new \Exception('Unable to find the order');
		}
		/* @var $order StoreOrder */

		$custID = $order->getCustomerID();
		$customer = new StoreCustomer($custID);

		$transaction = [
			'Customer' => [
				'FirstName' => $customer->getValue("billing_first_name"),
				'LastName' => $customer->getValue("billing_last_name"),
				'Street1' => $customer->getValue("billing_address")->address1,
				'Street2' => $customer->getValue("billing_address")->address2,
				'City' => $customer->getValue("billing_address")->city,
				'State' => $customer->getValue("billing_address")->state_province,
				'PostalCode' => $customer->getValue("billing_address")->postal_code,
				'Country' => $customer->getValue("billing_address")->country,
				'Email' => $customer->getEmail(),
			],
			'ShippingAddress' => [
				'FirstName' => $customer->getValue("billing_first_name"),
				'LastName' => $customer->getValue("billing_last_name"),
				'Street1' => $customer->getValue("billing_address")->address1,
				'Street2' => $customer->getValue("billing_address")->address2,
				'City' => $customer->getValue("billing_address")->city,
				'State' => $customer->getValue("billing_address")->state_province,
				'PostalCode' => $customer->getValue("billing_address")->postal_code,
				'Country' => $customer->getValue("billing_address")->country,
				'Email' => $customer->getEmail(),
			],
			'RedirectUrl' => (string) \URL::to('/checkout/ewayreturn'),
			'CancelUrl' => (string) \URL::to('/checkout'),
			'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
			'Payment' => [
				'TotalAmount' => floor($order->getTotal() * 100), // This is in cents
				'InvoiceNumber' => (int) $oid,
				// 'InvoiceDescription' =>
				// 'InvoiceReference' =>
				'CurrencyCode' => Config::get('community_store_eway.ewayCurrency')
			],
			// You can apparently pass in a whole shedload of other stuff here to help with fraud detection etc
			// See eway docs for more info.
		];

		// Submit data to eWAY to get a Shared Page URL
		$response = $client->createTransaction(\Eway\Rapid\Enum\ApiMethod::RESPONSIVE_SHARED, $transaction);
		/* @$response Eway\Rapid\Model\Response\CreateTransactionResponse */
		if (!$response->getErrors()) {
			$sharedURL = $response->SharedPaymentUrl;
			$this->redirect($sharedURL);
		} else {
			\Log::addEntry(__METHOD__ . " client->createTransaction failed\nError:\n" . implode("\n", $response->getErrors()), 'Eway');
			throw new \Exception('Error communicating with card gateway');
		}
	}


	public function EwayReturn () {
		$client = $this->makeClient();

		$response = $client->queryTransaction($this->get('AccessCode'));
		$transactionResponse = $response->Transactions[0];
		/* @var $transactionResponse \Eway\Rapid\Model\Transaction */

		// Get the transaction result
		if (!$transactionResponse->TransactionStatus) {
			$errors = explode(', ', $transactionResponse->ResponseMessage);
			$errorString = '';
			foreach ($errors as $error) {
				$errorString .= "Payment failed: " .
					\Eway\Rapid::getMessage($error) . "<br>";
				Session::set('paymentErrors', (string) $errorString);
				$this->redirect('/checkout/failed');
			}
		}

		$oid = (int) $transactionResponse->InvoiceNumber;
		$order = StoreOrder::getByID($oid);
		if (!$order) {
			\Log::addEntry(t('Fatal: no such order ' . $transactionResponse->InvoiceNumber), 'Eway');
			throw new \Exception('Fatal: Eway: no such order');
		}

		/* @var $order StoreOrder */

		if (!$order->getTransactionReference()) {
			$order->completeOrder((string) $transactionResponse->TransactionID);
		}

		$this->redirect('/checkout/complete');
	}


	private function makeClient () {
		$apiKey = Config::get('community_store_eway.ewayAPIKey');
		$pass = Config::get('community_store_eway.ewayPassword');
		$endpoint = Config::get('community_store_eway.ewaySandbox') ? 'sandbox' : 'production';

		return \Eway\Rapid::createClient($apiKey, $pass, $endpoint);
	}


	public function dashboardForm () {
		$this->set('ewaySandbox', Config::get('community_store_eway.ewaySandbox'));
		$this->set('ewayPassword', Config::get('community_store_eway.ewayPassword'));
		$this->set('ewayAPIKey', Config::get('community_store_eway.ewayAPIKey'));
		$this->set('ewayCurrency', Config::get('community_store_eway.ewayCurrency'));
		$currencies = array( // There may be others
			// https://go.eway.io/s/article/What-currency-codes-does-eWAY-use
			'AUD' => "Australian Dollar",
			'NZD' => "New Zealand Dollar",
			'USD' => "U.S. Dollar"
		);
		$this->set('currencies', $currencies);
		$this->set('form', Core::make("helper/form"));
	}


	public function save (array $data = []) {
		Config::save('community_store_eway.ewaySandbox', $data['ewaySandbox']);
		Config::save('community_store_eway.ewayPassword', $data['ewayPassword']);
		Config::save('community_store_eway.ewayAPIKey', $data['ewayAPIKey']);
		Config::save('community_store_eway.ewayCurrency', $data['ewayCurrency']);
	}


	public function validate ($args, $e) {
		$pm = StorePaymentMethod::getByHandle('community_store_eway');
		if ($args['paymentMethodEnabled'][$pm->getID()] == 1) {
			if ($args['ewayPassword'] == "") {
				$e->add(t("Password must be set"));
			}
			if ($args['ewayAPIKey'] == "") {
				$e->add(t("API key must be set"));
			}
		}

		return $e;

	}

	public function getName () {
		return 'Eway';
	}


	public function isExternal () {
		return true;
	}
}