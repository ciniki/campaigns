<?php
//
// Description
// -----------
// The module flags
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_campaigns_maps($ciniki) {
	$maps = array();
	$maps['campaign'] = array(
		'status'=>array(
			'0'=>'Entered',
			'10'=>'Active',
			'50'=>'Inactive',
			'60'=>'Deleted',
		));
	$maps['email'] = array(
		'status'=>array(
			'0'=>'Unknown',
			'10'=>'Active',
			'50'=>'Inactive',
			'60'=>'Deleted',
		));
	$maps['customer'] = array(
		'status'=>array(
			'0'=>'Unknown',
			'10'=>'Active',
			'20'=>'Stopped',
			'30'=>'Successful',
			'40'=>'Completed',
			'50'=>'Unsubscribed',
		));

	return array('stat'=>'ok', 'maps'=>$maps);
}
?>
