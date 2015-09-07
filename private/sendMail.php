<?php
//
// Description
// -----------
// This function will prepare and add a campaign email to the ciniki.mail module for sending. 
// This function could be run via the API and will trigger the email to send immediately, or if it's
// run via CRON then the emails will be picked up when the mail queue is processed.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_campaigns_sendMail(&$ciniki, $business_id, $queue_id) {

	//
	// Load the email from the queue
	//
	$strsql = "SELECT ciniki_campaign_queue.campaign_id, "	
		. "ciniki_campaign_queue.campaign_customer_id, "
		. "ciniki_campaign_queue.campaign_email_id, "
		. "ciniki_campaign_queue.send_date, "
		. "ciniki_campaign_queue.status AS queue_status, "
		. "ciniki_campaigns.tracking_uuid, "
		. "ciniki_campaigns.status AS campaign_status, "
		. "ciniki_campaign_emails.status AS email_status, "
		. "ciniki_campaign_emails.subject, "
		. "ciniki_campaign_emails.html_content, "
		. "ciniki_campaign_emails.text_content, "
		. "ciniki_campaign_customers.customer_id, "
		. "ciniki_campaign_customers.status AS customer_status "
		. "FROM ciniki_campaign_queue "
		. "INNER JOIN ciniki_campaigns ON ("
			. "ciniki_campaign_queue.campaign_id = ciniki_campaigns.id "
			. "AND ciniki_campaigns.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. ") "
		. "INNER JOIN ciniki_campaign_emails ON ("
			. "ciniki_campaign_queue.campaign_email_id = ciniki_campaign_emails.id "
			. "AND ciniki_campaign_emails.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. ") "
		. "INNER JOIN ciniki_campaign_customers ON ("
			. "ciniki_campaign_queue.campaign_customer_id = ciniki_campaign_customers.id "
			. "AND ciniki_campaign_customers.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. ") "
		. "WHERE ciniki_campaign_queue.id = '" . ciniki_core_dbQuote($ciniki, $queue_id) . "' "
		. "AND ciniki_campaign_queue.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'email');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['email']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2534', 'msg'=>'Unable to find queue email'));
	}
	$email = $rc['email'];

	//
	// Check the status of the queue item
	//
	if( $email['queue_status'] != '10' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2536', 'msg'=>'Queued email is not ready to send.'));
	}

	//
	// Check the status of the campaign to make sure it's still active
	//
	if( $email['campaign_status'] != '10' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2537', 'msg'=>'Campaign is inactive'));
	}

	//
	// Check the status of the campaign customer
	//
	if( $email['campaign_status'] != '10' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2538', 'msg'=>'Campaign customer is inactive'));
	}

	//
	// Check the status of the campaign email
	//
	if( $email['campaign_status'] != '10' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2539', 'msg'=>'Campaign email is inactive'));
	}
	
	//
	// Get customer details
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerEmails');
	$rc = ciniki_customers_hooks_customerEmails($ciniki, $business_id, array('customer_id'=>$email['customer_id']));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['customer']['emails']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2543', 'msg'=>'Customer does not have any emails.'));
	}
	$customer = $rc['customer'];

	//
	// Parse and do substitutions on html and text content
	//



	//
	// Update status of queue item to lock it
	//
	$strsql = "UPDATE ciniki_campaign_queue SET status = 20 "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $queue_id) . "' "
		. "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND status = 10 "
		. "";
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.campaigns');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Insert into the ciniki_mail modules
	//
	foreach($customer['emails'] as $customer_email) {
		$email_args = array(
			'customer_id'=>$email['customer_id'],
			'customer_name'=>$customer['display_name'],
			'customer_email'=>$customer_email['address'],
			'subject'=>$email['subject'],
			'html_content'=>$email['html_content'],
			'text_content'=>$email['text_content'],
			'object'=>'ciniki.campaigns.email',
			'object_id'=>$email['campaign_email_id'],
			'parent_object'=>'ciniki.campaigns.campaign',
			'parent_object_id'=>$email['campaign_id'],
			);

		//
		// Add to the emailqueue
		//
		$rc = ciniki_mail_hooks_addMessage($ciniki, $business_id, $email_args);
		if( $rc['stat'] != 'ok' ) {
			$rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.campaigns.queue', $queue_id, array('status'=>'40'), 0x04);
			if( $rc['stat'] != 'ok' ) {
				return $rc;
			}
		}

		//
		// If this is being run via API it will trigger the sending of the email.
		// If it is being run via cron, this will be ignored and picked up when it processes the mail queue
		//
		$ciniki['emailqueue'][] = array('email_id'=>$rc['id']);
	}

	return array('stat'=>'ok');
}
?>
