<?php
//
// Description
// -----------
// This method will add a new campaign for the business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:		The ID of the business to add the campaign to.
// name:			The name of the campaign.
// url:				(optional) The URL for the campaign website.
// description:		(optional) The description for the campaign.
// start_date:		(optional) The date the campaign starts.  
// end_date:		(optional) The date the campaign ends, if it's longer than one day.
//
// Returns
// -------
// <rsp stat="ok" id="42">
//
function ciniki_campaigns_campaignAdd(&$ciniki) {
	//
	// Find all the required and optional arguments
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		'name'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Name'), 
		'status'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Status'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id as owner
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'private', 'checkAccess');
	$rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignAdd');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Get a new tracking_uuid
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUUID');
	$rc = ciniki_core_dbUUID($ciniki, 'ciniki.campaigns');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args['tracking_uuid'] = $rc['uuid'];

	//
	// Check the tracking_uuid doesn't already exist
	//
	$strsql = "SELECT id "
		. "FROM ciniki_campaigns "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' " 
		. "AND tracking_uuid = '" . ciniki_core_dbQuote($ciniki, $args['tracking_uuid']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'campaign');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( $rc['num_rows'] > 0 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2512', 'msg'=>'We seem to have a problem setting up this campaign, please try again.'));
	}

	//
	// Start transaction
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
	// Add the campaign to the database
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
	$rc = ciniki_core_objectAdd($ciniki, $args['business_id'], 'ciniki.campaigns.campaign', $args, 0x04);
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.campaigns');
		return $rc;
	}
	$campaign_id = $rc['id'];

	//
	// Commit the transaction
	//
	$rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.campaigns');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'campaigns');

	return array('stat'=>'ok', 'id'=>$campaign_id);
}
?>
