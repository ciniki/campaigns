<?php
//
// Description
// -----------
// This function checks for messages in the campaign queue that are ready to send and 
// add thems to the ciniki.mail module to get picked up and sent via cron.
//
// Arguments
// ---------
// ciniki:
// 
// Returns
// -------
//
function ciniki_campaigns_cron_checkQueue($ciniki) {
	print("CRON: Checking campaigns queue for mail to be sent\n");
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList');

	//
	// Get the list of businesses which have mail waiting to be sent
	//
	$strsql = "SELECT DISTINCT business_id "
		. "FROM ciniki_campaign_queue "
		. "WHERE send_date <= UTC_TIMESTAMP() "
		. "AND status = 10 "
		. "";
	$rc = ciniki_core_dbQueryList($ciniki, $strsql, 'ciniki.mail', 'businesses', 'business_id');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['businesses']) || count($rc['businesses']) == 0 ) {
		$businesses = array();
	} else {
		$businesses = $rc['businesses'];
	}

	//
	// For each business, load their mail settings
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'private', 'getSettings');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'private', 'sendMail');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
	foreach($businesses as $business_id) {
		print("CRON: Sending campaign mail for $business_id\n");
//		$rc = ciniki_mail_getSettings($ciniki, $business_id);
//		if( $rc['stat'] != 'ok' ) {
//			error_log("CRON-ERR: Unable to load business mail settings for $business_id (" . serialize($rc) . ")");
//			continue;
//		}
//		$settings = $rc['settings'];	

//		$limit = 1; 	// Default to really slow sending, 1 every 5 minutes
//		if( isset($settings['smtp-5min-limit']) && is_numeric($settings['smtp-5min-limit']) && $settings['smtp-5min-limit'] > 0 ) {
//			$limit = intval($settings['smtp-5min-limit']);
//		}

		$strsql = "SELECT ciniki_campaign_queue.id "
			. "FROM ciniki_campaign_queue "
			. "WHERE ciniki_campaign_queue.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND ciniki_campaign_queue.status = 10 "
			. "";

		$rc = ciniki_core_dbQueryList($ciniki, $strsql, 'ciniki.campaigns', 'emails', 'id');
		if( $rc['stat'] != 'ok' ) {
			error_log("CRON-ERR: Unable to load mail list for $business_id (" . serialize($rc) . ")");
			continue;
		}
		$emails = $rc['emails'];
		foreach($emails as $queue_id) {
			//
			// Start transaction. Each mail message should be it's own transaction so as not to lock up the database.
			//
			ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
			ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
			ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
			ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
			$rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.campaigns');
			if( $rc['stat'] != 'ok' ) { 
				return $rc;
			}   

			//
			// Put the email in the ciniki.mail module
			//
			$rc = ciniki_campaigns_sendMail($ciniki, $business_id, $queue_id);
			if( $rc['stat'] != 'ok' ) {
				error_log("CRON-ERR: Unable to send campaign mail for $business_id (" . serialize($rc) . ")");
				continue;
			}

			//
			// Commit transaction
			//
			$rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.campaigns');
			if( $rc['stat'] != 'ok' ) {
				return $rc;
			}
		}
	}

	return array('stat'=>'ok');
}
