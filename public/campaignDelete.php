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
function ciniki_campaigns_campaignDelete(&$ciniki) {
	//
	// Find all the required and optional arguments
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		'campaign_id'=>array('required'=>'yes', 'default'=>'', 'blank'=>'yes', 'name'=>'Campaign'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id as owner
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'private', 'checkAccess');
	$rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignDelete');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Get the uuid of the campaign to be deleted
	//
	$strsql = "SELECT uuid "
		. "FROM ciniki_campaigns "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'campaign');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['campaign']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2509', 'msg'=>'The campaign does not exist'));
	}
	$campaign_uuid = $rc['campaign']['uuid'];

	//
	// Check if the campaign is still used by another module, this includes if any emails are still linked.
	//
	foreach($ciniki['business']['modules'] as $module => $m) {
		list($pkg, $mod) = explode('.', $module);
		$rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'hooks', 'checkObjectUsed');
		if( $rc['stat'] == 'ok' ) {
			$fn = $rc['function_call'];
			$rc = $fn($ciniki, $args['business_id'], array(
				'object'=>'ciniki.campaigns.campaign', 
				'object_id'=>$args['campaign_id'],
				));
			if( $rc['stat'] != 'ok' ) {
				return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2510', 'msg'=>'Unable to check if the campaign is still being used', 'err'=>$rc['err']));
			}
			if( $rc['used'] != 'no' ) {
				return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2511', 'msg'=>"Unable to remove campaign. " . $rc['msg']));
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
	// Remove the customers
	//
	$strsql = "SELECT id, uuid "
		. "FROM ciniki_campaign_customers "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND campaign_id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'item');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.campaigns');
		return $rc;
	}
	if( isset($rc['rows']) && count($rc['rows']) > 0 ) {
		$items = $rc['rows'];
		foreach($items as $iid => $item) {
			$rc = ciniki_core_objectDelete($ciniki, $args['business_id'], 'ciniki.campaigns.customer', 
				$item['id'], $item['uuid'], 0x04);
			if( $rc['stat'] != 'ok' ) {
				ciniki_core_dbTransactionRollback($ciniki, 'ciniki.campaigns');
				return $rc;	
			}
		}
	}

	//
	// Remove the emails
	//
	$strsql = "SELECT id, uuid "
		. "FROM ciniki_campaign_emails "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND campaign_id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'item');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.campaigns');
		return $rc;
	}
	if( isset($rc['rows']) && count($rc['rows']) > 0 ) {
		$items = $rc['rows'];
		foreach($item as $iid => $item) {
			$rc = ciniki_core_objectDelete($ciniki, $args['business_id'], 'ciniki.campaigns.email', 
				$item['id'], $item['uuid'], 0x04);
			if( $rc['stat'] != 'ok' ) {
				ciniki_core_dbTransactionRollback($ciniki, 'ciniki.campaigns');
				return $rc;	
			}
		}
	}

	//
	// Remove the campaign
	//
	$rc = ciniki_core_objectDelete($ciniki, $args['business_id'], 'ciniki.campaigns.campaign', 
		$args['campaign_id'], $campaign_uuid, 0x04);
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
