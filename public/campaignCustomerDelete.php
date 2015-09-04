<?php
//
// Description
// -----------
// This method will delete a campaign from the business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:			The ID of the business the campaign is attached to.
// campaign_id:			The ID of the campaign to be removed.
//
// Returns
// -------
// <rsp stat="ok">
//
function ciniki_campaigns_campaignCustomerDelete(&$ciniki) {
	//
	// Find all the required and optional arguments
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		'campaign_customer_id'=>array('required'=>'yes', 'default'=>'', 'blank'=>'yes', 'name'=>'Customer'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id as owner
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'private', 'checkAccess');
	$rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignCustomerDelete');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Get the uuid of the campaign to be deleted
	//
	$strsql = "SELECT uuid "
		. "FROM ciniki_campaign_customers "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_customer_id']) . "' "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'customer');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['customer']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2523', 'msg'=>'The campaign customer does not exist'));
	}
	$customer_uuid = $rc['customer']['uuid'];

	//
	// Check if the campaign is still used by another module, this includes if any customers are still linked.
	//
	foreach($ciniki['business']['modules'] as $module => $m) {
		list($pkg, $mod) = explode('.', $module);
		$rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'hooks', 'checkObjectUsed');
		if( $rc['stat'] == 'ok' ) {
			$fn = $rc['function_call'];
			$rc = $fn($ciniki, $args['business_id'], array(
				'object'=>'ciniki.campaigns.customer', 
				'object_id'=>$args['customer_id'],
				));
			if( $rc['stat'] != 'ok' ) {
				return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2524', 'msg'=>'Unable to check if the campaign customer is still being used', 'err'=>$rc['err']));
			}
			if( $rc['used'] != 'no' ) {
				return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2525', 'msg'=>"Unable to remove campaign customer. " . $rc['msg']));
			}
		}
	}

	//
	// Start transaction
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.campaigns');
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	//
	// Remove the campaign customer
	//
	$rc = ciniki_core_objectDelete($ciniki, $args['business_id'], 'ciniki.campaigns.customer', 
		$args['customer_id'], $customer_uuid, 0x04);
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.campaigns');
		return $rc;
	}

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

	return array('stat'=>'ok');
}
?>
