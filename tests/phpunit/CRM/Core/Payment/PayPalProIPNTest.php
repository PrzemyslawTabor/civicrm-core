<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use Civi\Api4\Contribution;

/**
 * Class CRM_Core_Payment_PayPalProIPNTest
 * @group headless
 */
class CRM_Core_Payment_PayPalProIPNTest extends CiviUnitTestCase {
  protected $_contributionID;
  protected $_invoiceID = 'c2r9c15f7be20b4f3fef1f77e4c37424';
  protected $_financialTypeID = 1;
  protected $_contactID;
  protected $_contributionRecurID;
  protected $_paymentProcessorID;

  /**
   * Set up function.
   */
  public function setUp(): void {
    parent::setUp();
    $this->_paymentProcessorID = $this->paymentProcessorCreate(['is_test' => 0]);
    $this->_contactID = $this->individualCreate();
    $contributionPage = $this->callAPISuccess('contribution_page', 'create', [
      'title' => 'Test Contribution Page',
      'financial_type_id' => $this->_financialTypeID,
      'currency' => 'USD',
      'payment_processor' => $this->_paymentProcessorID,
    ]);
    $this->ids['ContributionPage'][0] = $contributionPage['id'];
  }

  /**
   * Tear down function.
   */
  public function tearDown(): void {
    $this->quickCleanUpFinancialEntities();
    parent::tearDown();
  }

  /**
   * Test IPN response updates contribution_recur & contribution for first &
   * second contribution.
   *
   * The scenario is that a pending contribution exists and the first call will
   * update it to completed. The second will create a new contribution.
   *
   * @throws \CRM_Core_Exception
   */
  public function testIPNPaymentRecurSuccess(): void {
    $this->disorganizeOptionValues();
    $this->setupRecurringPaymentProcessorTransaction();
    global $_GET;
    $_GET = $this->getPaypalProRecurTransaction();
    $paypalIPN = new CRM_Core_Payment_PayPalProIPN($this->getPaypalProRecurTransaction());
    $paypalIPN->main();
    $contribution = Contribution::get()->addWhere('id', '=', $this->ids['Contribution']['default'])
      ->addSelect('contribution_status_id:name', 'trxn_id', 'source')
      ->execute()->first();
    $this->assertEquals('Completed', $contribution['contribution_status_id:name']);
    $this->assertEquals('8XA571746W2698126', $contribution['trxn_id']);
    // source gets set by processor
    $this->assertEquals('Online Contribution:', substr($contribution['source'], 0, 20));

    // Re-try the IPN and confirm that a second contribution is not
    // created (this relies on the trxn_id being the same).
    $paypalIPN = new CRM_Core_Payment_PayPalProIPN($this->getPaypalProRecurTransaction());
    $paypalIPN->main();
    $contributions = Contribution::get()->addWhere('contribution_recur_id', '=', $this->ids['ContributionRecur']['default'])
      ->addSelect('contribution_status_id:name', 'trxn_id', 'source')
      ->execute();
    $this->assertCount(1, $contributions);
    // source gets set by processor
    $contributionRecur = $this->callAPISuccess('contribution_recur', 'getsingle', ['id' => $this->ids['ContributionRecur']['default']]);
    $this->assertEquals(5, $contributionRecur['contribution_status_id']);
    $paypalIPN = new CRM_Core_Payment_PayPalProIPN($this->getPaypalProRecurSubsequentTransaction());
    $paypalIPN->main();
    $contribution = $this->callAPISuccess('Contribution', 'get', [
      'contribution_recur_id' => $this->_contributionRecurID,
      'sequential' => 1,
    ]);
    $this->assertEquals(2, $contribution['count']);
    $this->assertEquals('second-one', $contribution['values'][1]['trxn_id']);
    $this->assertEquals('Debit Card', $contribution['values'][1]['payment_instrument']);
  }

  /**
   * Test IPN response updates contribution_recur & contribution for first & second contribution.
   *
   * @throws \CRM_Core_Exception
   */
  public function testIPNPaymentMembershipRecurSuccess(): void {
    $durationUnit = 'year';
    $this->setupMembershipRecurringPaymentProcessorTransaction(['duration_unit' => $durationUnit, 'frequency_unit' => $durationUnit]);
    $this->callAPISuccessGetSingle('membership_payment', []);
    $paypalIPN = new CRM_Core_Payment_PayPalProIPN($this->getPaypalProRecurTransaction());
    $paypalIPN->main();
    $contribution = $this->callAPISuccess('contribution', 'getsingle', ['id' => $this->_contributionID]);
    $membershipEndDate = $this->callAPISuccessGetValue('membership', ['return' => 'end_date']);
    $this->assertEquals(1, $contribution['contribution_status_id']);
    $this->assertEquals('8XA571746W2698126', $contribution['trxn_id']);
    // source gets set by processor
    $this->assertEquals('Online Contribution:', substr($contribution['contribution_source'], 0, 20));
    $contributionRecur = $this->callAPISuccess('contribution_recur', 'getsingle', ['id' => $this->_contributionRecurID]);
    $this->assertEquals(5, $contributionRecur['contribution_status_id']);
    $paypalIPN = new CRM_Core_Payment_PayPalProIPN($this->getPaypalProRecurSubsequentTransaction());
    $paypalIPN->main();

    $renewedMembershipEndDate = $this->membershipRenewalDate($durationUnit, $membershipEndDate);
    $this->assertEquals($renewedMembershipEndDate, $this->callAPISuccessGetValue('membership', ['return' => 'end_date']));
    $contribution = $this->callAPISuccess('contribution', 'get', [
      'contribution_recur_id' => $this->_contributionRecurID,
      'sequential' => 1,
    ]);
    $this->assertEquals(2, $contribution['count']);
    $this->assertEquals('second-one', $contribution['values'][1]['trxn_id']);
    $this->callAPISuccessGetCount('line_item', [
      'entity_id' => $this->ids['membership'],
      'entity_table' => 'civicrm_membership',
    ], 2);
    $this->callAPISuccessGetSingle('line_item', [
      'contribution_id' => $contribution['values'][1]['id'],
      'entity_table' => 'civicrm_membership',
    ]);
    $this->callAPISuccessGetSingle('membership_payment', ['contribution_id' => $contribution['values'][1]['id']]);

  }

  /**
   * CRM-13743 test IPN edge case where the first transaction fails and the
   * second succeeds.
   *
   * We are checking that the created contribution has the same date as IPN
   * says it should Note that only one contribution will be created (no
   * evidence of the failed contribution is left) It seems likely that may
   * change in future & this test will start failing (I point this out in the
   * hope it will help future debuggers)
   *
   * @throws \CRM_Core_Exception
   */
  public function testIPNPaymentCRM13743(): void {
    $this->setupRecurringPaymentProcessorTransaction();
    $firstPaymentParams = $this->getPaypalProRecurTransaction();
    $firstPaymentParams['txn_type'] = 'recurring_payment_failed';
    $paypalIPN = new CRM_Core_Payment_PayPalProIPN($firstPaymentParams);
    $paypalIPN->main();

    $contribution = $this->callAPISuccess('contribution', 'getsingle', ['id' => $this->_contributionID]);
    $this->assertEquals(2, $contribution['contribution_status_id']);
    $this->assertEquals('', $contribution['trxn_id']);
    $contributionRecur = $this->callAPISuccess('contribution_recur', 'getsingle', ['id' => $this->_contributionRecurID]);
    $this->assertEquals(2, $contributionRecur['contribution_status_id']);
    $paypalIPN = new CRM_Core_Payment_PayPalProIPN($this->getPaypalProRecurSubsequentTransaction());
    $paypalIPN->main();
    $contribution = $this->callAPISuccess('contribution', 'get', [
      'contribution_recur_id' => $this->_contributionRecurID,
      'sequential' => 1,
    ]);
    $this->assertEquals(1, $contribution['count']);
    $this->assertEquals('second-one', $contribution['values'][0]['trxn_id']);
    $this->assertEquals(strtotime('03:59:05 Jul 14, 2013 PDT'), strtotime($contribution['values'][0]['receive_date']));
  }

  /**
   * Check a payment express IPN call does not throw any errors.
   *
   * At this stage nothing it supposed to happen so it's a pretty blunt test
   * but at least it should be e-notice free
   * The browser interaction will update Paypal express payments
   * The ipn code redirects POSTs to paypal pro & GETs to paypal std but the
   * documentation (https://www.paypalobjects.com/webstatic/en_US/developer/docs/pdf/ipnguide.pdf)
   * implies only POSTS are sent server to server.
   * So, it's likely Paypal Std IPNs aren't working.
   * However, for Paypal Pro users payment express transactions can't work as they don't hold the component
   * which is required for them to be handled by either the Pro or Express class
   *
   * So, the point of this test is simply to ensure it fails in a known way
   */
  public function testIPNPaymentExpressNoError(): void {
    $this->setupRecurringPaymentProcessorTransaction();
    $paypalIPN = new CRM_Core_Payment_PayPalProIPN($this->getPaypalExpressTransactionIPN());
    $paypalIPN->main();
    $contribution = $this->callAPISuccess('contribution', 'getsingle', ['id' => $this->_contributionID]);
    // no change
    $this->assertEquals(2, $contribution['contribution_status_id']);
  }

  /**
   * Get PaymentExpress IPN for a single transaction.
   * @return array
   *   array representing a Paypal IPN POST
   */
  public function getPaypalExpressTransactionIPN(): array {
    return [
      'mc_gross' => '200.00',
      'invoice' => 'xyz',
      'protection_eligibility' => 'Eligible',
      'address_status' => 'confirmer',
      'payer_id' => 'ZYXHBZSULPQE3',
      'tax' => '0.00',
      'address_street' => '13 Streety Street',
      'payment_rate' => '03:32:12 Jul 29, 2013 PDT',
      'payment_status' => 'Completed',
      'charset' => 'windows-1252',
      'address_zip' => '90210',
      'first_name' => 'Mary-Jane',
      'mc_fee' => '4.70',
      'address_country_core' => 'US',
      'address_name' => 'Mary-Jane',
      'notify_version' => '3.7',
      'custom' => '',
      'payer_status' => 'unverified',
      'address_country' => 'UNITED STATES',
      'address_city' => 'Portland',
      'quantity' => '1',
      'verify_sign' => 'AUyUU3IMAvssa3j4KorlbLnfr.9.AW7GX-sL7Ts1brCHvn13npvO-pqf',
      'payer_email' => 'mary@nowhere.com',
      'txn_id' => '3X9131350B932393N',
      'payment_type' => 'instant',
      'last_name' => 'Bob',
      'address_state' => 'ME',
      'receiver_email' => 'email@civicrm.org',
      'payment_fee' => '4.70',
      'received_id' => 'GUH3W7BJLGTY3',
      'txn_type' => 'express_checkout',
      'item_name' => '',
      'mc_currency' => 'USD',
      'item_number' => '',
      'residence_country' => 'US',
      'handling_amount' => '0.00',
      'transaction_subject' => '',
      'payment_gross' => '200.00',
      'shipping' => '0.00',
      'ipn_track_id' => '5r27c2e31rl7c',
      'is_unit_test' => TRUE,
    ];
  }

  /**
   * Get IPN results from follow on IPN transactions.
   * @return array
   *   array representing a Paypal IPN POST
   */
  public function getSubsequentPaypalExpressTransaction(): array {
    return [
      'mc_gross' => '5.00',
      'period_type' => ' Regular',
      'outstanding_balance' => '0.00',
      'next_payment_date' => '03:00:00 Aug 14, 2013 PDT',
      'protection_eligibility' => 'Eligible',
      'payment_cycle' => 'Monthly',
      'address_status' => 'confirmed',
      'tax' => '0.00',
      'payer_id' => 'ACRAM59AAS2E4',
      'address_street' => '54 Soul Street',
      'payment_date' => '03:58:39 Jul 14, 2013 PDT',
      'payment_status' => 'Completed',
      'product_name' => '5 Per 1 month',
      'charset' => 'windows-1252',
      'rp_invoice_id' => 'i=xyz&m=&c=&r=&b=&p=' . $this->ids['ContributionPage'][0],
      'recurring_payment_id' => 'I-3EEUC094KYQW',
      'address_zip' => '90210',
      'first_name' => 'Alanna',
      'mc_fee' => '0.41',
      'address_country_code' => 'US',
      'address_name' => 'Alanna Morrissette',
      'notify_version' => '3.7',
      'amount_per_cycle' => '5.00',
      'payer_status' => 'unverified',
      'currency_code' => 'USD',
      'business' => 'mpa@example.com',
      'address_country' => 'UNITED STATES',
      'address_city' => 'Limestone',
      'verify_sign' => 'AXi4DULbes8quzIiq2YNsdTJH5ciPPPzG9PcQvkQg4BjfvWi8aY9GgDb',
      'payer_email' => 'passport45051@yahoo.com',
      'initial_payment_amount' => '0.00',
      'profile_status' => 'Active',
      'amount' => '5.00',
      'txn_id' => '03W6561902100533N',
      'payment_type' => 'instant',
      'last_name' => 'Morrissette',
      'address_state' => 'ME',
      'receiver_email' => 'info@example.com',
      'payment_fee' => '0.41',
      'receiver_id' => 'GTH8P7UQWWTY6',
      'txn_type' => 'recurring_payment',
      'mc_currency' => 'USD',
      'residence_country' => 'US',
      'transaction_subject' => '5 Per 1 month',
      'payment_gross' => '5.00',
      'shipping' => '0.00',
      'product_type' => '1',
      'time_created' => '12:02:25 May 14, 2013 PDT',
      'ipn_track_id' => '912e5010eb5a6',
    ];
  }

  /**
   * Get IPN style details for an incoming recurring transaction.
   */
  public function getPaypalProRecurTransaction(): array {
    return [
      'amount' => '15.00',
      'initial_payment_amount' => '0.00',
      'profile_status' => 'Active',
      'payer_id' => '4NHUTA7ZUE92C',
      'product_type' => '1',
      'ipn_track_id' => '30171ad0afe3g',
      'outstanding_balance' => '0.00',
      'shipping' => '0.00',
      'charset' => 'windows-1252',
      'period_type' => ' Regular',
      'payment_gross' => '15.00',
      'currency_code' => 'USD',
      'receipt_id' => '1428-3355-5949-8495',
      'verify_sign' => 'AoPC4BjkCyDFEXbSkoZcgqH3hpacA3RXyCD10axGfqyaRhHqwz1UZzX7',
      'payment_cycle' => 'Monthly',
      'txn_type' => 'recurring_payment',
      'receiver_id' => 'GWE8P7BJVLMY6',
      'payment_fee' => '0.63',
      'mc_currency' => 'USD',
      'transaction_subject' => '',
      'protection_eligibility' => 'Ineligible',
      'payer_status' => 'unverified',
      'first_name' => 'Robert',
      'product_name' => ' =>  15 Per 1 month',
      'amount_per_cycle' => '15.00',
      'mc_gross' => '15.00',
      'payment_date' => '03:59:05 Jul 14, 2013 PDT',
      'rp_invoice_id' => 'i=xyz&m=contribute&c=' . $this->_contactID . '&r=' . $this->ids['ContributionRecur']['default'] . '&b=' . $this->ids['Contribution']['default'] . '&p=null',
      'payment_status' => 'Completed',
      'business' => 'nowhere@civicrm.org',
      'last_name' => 'Roberty',
      'txn_id' => '8XA571746W2698126',
      'mc_fee' => '0.63',
      'time_created' => '14 => 51 => 55 Feb 14, 2013 PST',
      'resend' => 'true',
      'payment_type' => 'instant',
      'notify_version' => '3.7',
      'recurring_payment_id' => 'I-8XHAKBG12SFP',
      'receiver_email' => 'nil@civicrm.org',
      'next_payment_date' => '03:00:00 Aug 14, 2013 PDT',
      'tax' => '0.00',
      'residence_country' => 'US',
    ];
  }

  /**
   * Get IPN-style details for a second incoming transaction.
   *
   * @return array
   */
  public function getPaypalProRecurSubsequentTransaction(): array {
    return array_merge($this->getPaypalProRecurTransaction(), ['txn_id' => 'second-one']);
  }

  /**
   * Test IPN response update for a paypal express profile creation confirmation.
   */
  public function testIPNPaymentExpressRecurSuccess(): void {
    $this->setupRecurringPaymentProcessorTransaction(['processor_id' => '']);
    $paypalIPN = new CRM_Core_Payment_PayPalProIPN($this->getPaypalExpressRecurSubscriptionConfirmation());
    $paypalIPN->main();
    $contributionRecur = $this->callAPISuccess('contribution_recur', 'getsingle', ['id' => $this->_contributionRecurID]);
    $this->assertEquals('I-JW77S1PY2032', $contributionRecur['processor_id']);
  }

  /**
   * Get response consistent with creating a new profile.
   *
   * @return array
   */
  public function getPaypalExpressRecurSubscriptionConfirmation() {
    return [
      'payment_cycle' => 'Monthly',
      'txn_type' => 'recurring_payment_profile_created',
      'last_name' => 'buyer',
      'next_payment_date' => '03:00:00 May 09, 2018 PDT',
      'residence_country' => 'GB',
      'initial_payment_amount' => '0.00',
      'rp_invoice_id' => 'i=xyz'
      . '&m=&c=' . $this->_contributionID
      . '&r=' . $this->_contributionRecurID
      . '&b=' . $this->_contactID
      . '&p=' . $this->ids['ContributionPage'][0],
      'currency_code' => 'GBP',
      'time_created' => '12:39:01 May 09, 2018 PDT',
      'verify_sign' => 'AUg223oCjn4HgJXKkrICawXQ3fyUA2gAd1.f1IPJ4r.9sln-nWcB-EJG',
      'period_type' => 'Regular',
      'payer_status' => 'verified',
      'test_ipn' => '1',
      'tax' => '0.00',
      'payer_email' => 'payer@example.com',
      'first_name' => 'test',
      'receiver_email' => 'shop@example.com',
      'payer_id' => 'BWXXXM8111HDS',
      'product_type' => 1,
      'shipping' => '0.00',
      'amount_per_cycle' => '6.00',
      'profile_status' => 'Active',
      'charset' => 'windows-1252',
      'notify_version' => '3.9',
      'amount' => '6.00',
      'outstanding_balance' => '0.00',
      'recurring_payment_id' => 'I-JW77S1PY2032',
      'product_name' => '6 Per 1 month',
      'ipn_track_id' => '6255554274055',
    ];
  }

}
