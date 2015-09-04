<?php
//
// Description
// -----------
// This method will add a new campaign customer for the business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:		The ID of the business to add the campaign to.
//
// Returns
// -------
// <rsp stat="ok" id="42">
//
function ciniki_campaigns_campaignCustomerAdd(&$ciniki) {
	//
	// Find all the required and optional arguments
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		'campaign_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Campaign'), 
		'customer_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer'), 
		'start_date'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Start Date'), 
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
	$rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignCustomerAdd');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Check to make sure the customer does not already exist for the campaign
	//
	$strsql = "SELECT id, customer_id "
		. "FROM ciniki_campaign_customers "
		. "WHERE ciniki_campaign_customers.campaign_id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
		. "AND ciniki_campaign_customers.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
		. "AND ciniki_campaign_customers.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'customer');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( isset($rc['customer']) || count($rc['rows']) > 0 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2519', 'msg'=>'This customer is already part of this campaign.'));
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
	// Add the campaign customer to the database
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
	$rc = ciniki_core_objectAdd($ciniki, $args['business_id'], 'ciniki.campaigns.customer', $args, 0x04);
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
