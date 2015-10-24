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
function ciniki_campaigns_cron_jobs($ciniki) {
	ciniki_cron_logMsg($ciniki, 0, array('code'=>'0', 'msg'=>'Check for campaign jobs', 'severity'=>'5'));

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
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2623', 'msg'=>'Unable to get list of businesses with campaigns', 'err'=>$rc['err']));
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
		ciniki_cron_logMsg($ciniki, $business_id, array('code'=>'0', 'msg'=>'Sending campaign mail', 'severity'=>'10'));

		$strsql = "SELECT ciniki_campaign_queue.id "
			. "FROM ciniki_campaign_queue "
			. "WHERE ciniki_campaign_queue.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND ciniki_campaign_queue.status = 10 "
			. "";

		$rc = ciniki_core_dbQueryList($ciniki, $strsql, 'ciniki.campaigns', 'emails', 'id');
		if( $rc['stat'] != 'ok' ) {
			ciniki_cron_logMsg($ciniki, $business_id, array('code'=>'2624', 'msg'=>'Unable to load mail list', 
				'severity'=>50, 'err'=>$rc['err']));
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
				ciniki_cron_logMsg($ciniki, $business_id, array('code'=>'2626', 'msg'=>'Unable to setup transaction.', 
					'severity'=>50, 'err'=>$rc['err']));
				continue;
			}   

			//
			// Put the email in the ciniki.mail module
			//
			$rc = ciniki_campaigns_sendMail($ciniki, $business_id, $queue_id);
			if( $rc['stat'] != 'ok' ) {
				ciniki_cron_logMsg($ciniki, $business_id, array('code'=>'2625', 'msg'=>'Error sending campaign mail', 
					'severity'=>50, 'err'=>$rc['err']));
				continue;
			}

			//
			// Commit transaction
			//
			$rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.campaigns');
			if( $rc['stat'] != 'ok' ) {
				ciniki_cron_logMsg($ciniki, $business_id, array('code'=>'2627', 'msg'=>'Unable to close transaction.', 
					'severity'=>50, 'err'=>$rc['err']));
			}
		}
	}

	return array('stat'=>'ok');
}
