//
// This app will handle the listing, additions and deletions of campaigns.  These are associated business.
//
function ciniki_campaigns_main() {
	//
	// Panels
	//
	this.regFlags = {
		'1':{'name':'Track Registrations'},
		'2':{'name':'Online Registrations'},
		};
    //
    // campaigns panel
    //
    this.menu = new M.panel('Campaigns',
        'ciniki_campaigns_main', 'menu',
        'mc', 'medium', 'sectioned', 'ciniki.campaigns.main.menu');
    this.menu.sections = {
        'campaigns':{'label':'Campaigns', 'type':'simplegrid', 'num_cols':5,
            'sortable':'yes', 'sortTypes':['text', 'text', 'number', 'number', 'number'],
            'cellClasses':['', ''],
            'noData':'No campaigns',
            'addTxt':'Add Campaign',
            'addFn':'M.ciniki_campaigns_main.campaign.edit(\'M.ciniki_campaigns_main.menu.open();\',0);',
            },
        };
    this.menu.sectionData = function(s) { return this.data[s]; }
    this.menu.cellValue = function(s, i, j, d) {
        switch(j) {
            case 0: return d.campaign.status_text;
            case 1: return d.campaign.name;
            case 2: return d.campaign.num_active;
            case 3: return d.campaign.num_successful;
            case 4: return d.campaign.num_completed;
        }
    };
    this.menu.rowFn = function(s, i, d) {
        return 'M.ciniki_campaigns_main.campaign.show(\'M.ciniki_campaigns_main.menu.open();\',\'' + d.campaign.id + '\');';
    };
	this.menu.open = function(cb, cat) {
		this.menu.data = {};
		M.api.getJSONCb('ciniki.campaigns.campaignList', {'business_id':M.curBusinessID}, function(rsp) {
			if( rsp.stat != 'ok' ) {
				M.api.err(rsp);
				return false;
			}
			var p = M.ciniki_campaigns_main.menu;
			if( rsp.campaigns.length == 0 ) {
				p.sections.campaigns.headerValues = null;
			} else {
				p.sections.campaigns.headerValues = ['Status', 'Name', 'Active', 'Successful', 'Completed'];
			}
			p.data = rsp;
			p.refresh();
			p.show(cb);
		});
	};
    this.menu.addButton('add', 'Add', 'M.ciniki_campaigns_main.campaign.edit(\'M.ciniki_campaigns_main.menu.open();\',0);');
    this.menu.addClose('Back');

    //
    // The campaign panel 
    //
    this.campaign = new M.panel('Campaign',
        'ciniki_campaigns_main', 'campaign',
        'mc', 'medium mediumaside', 'sectioned', 'ciniki.campaigns.main.campaign');
    this.campaign.data = {};
    this.campaign.campaign_id = 0;
    this.campaign.sections = {
        'info':{'label':'', 'aside':'yes', 'list':{
            'name':{'label':'Name'},
            'status_text':{'label':'Status'},
            'delivery_time':{'label':'Delivery Time'},
            }},
        'emails':{'label':'Emails', 'aside':'yes', 'type':'simplegrid', 'num_cols':3,
            'headerValues':['Day', 'Status', 'Subject'],
            'addTxt':'Add Email',
            'addFn':'M.ciniki_campaigns_main.email.edit(\'M.ciniki_campaigns_main.campaign.show();\',0,M.ciniki_campaigns_main.campaign.campaign_id);',
            },
        'customer_stats':{'label':'Customers', 'type':'simplegrid', 'num_cols':2,
            'addTxt':'Add Customer',
            'addFn':'M.startApp(\'ciniki.customers.edit\',null,\'M.ciniki_campaigns_main.campaign.show();\',\'mc\',{\'next\':\'M.ciniki_campaigns_main.campaign.customerAdd\',\'customer_id\':0});',
            },
        '_buttons':{'label':'', 'buttons':{
            'edit':{'label':'Edit', 'fn':'M.ciniki_campaigns_main.campaign.edit(\'M.ciniki_campaigns_main.campaign.show();\',M.ciniki_campaigns_main.campaign.campaign_id);'},
            }},
    };
    this.campaign.sectionData = function(s) {
        if( s == 'info' ) { return this.sections[s].list; }
        return this.data[s];
    };
    this.campaign.listLabel = function(s, i, d) { return d.label; };
    this.campaign.listValue = function(s, i, d) { return this.data[i]; };
    this.campaign.cellValue = function(s, i, j, d) {
        if( s == 'customer_stats' ) { 
            switch(j) {
                case 0: return d.stat.status_text;
                case 1: return d.stat.num_customers;
            }
        } else if( s == 'emails' ) {
            switch(j) {
                case 0: return d.email.days_from_start;
                case 1: return d.email.status_text;
                case 2: return d.email.subject;
            }
        }
    };
    this.campaign.rowFn = function(s, i, d) {
        if( s == 'emails' ) {
            return 'M.ciniki_campaigns_main.email.edit(\'M.ciniki_campaigns_main.campaign.show();\',\'' + d.email.id + '\');';
        }
        return '';
    };
	this.campaign.show = function(cb, cid, customer_id) {
		this.campaign.reset();
		if( cid != null ) { this.campaign.campaign_id = cid; }
		var args = {'business_id':M.curBusinessID, 'campaign_id':this.campaign.campaign_id, 'stats':'yes', 'emails':'yes'};
		if( customer_id != null ) { args['add_customer_id'] = customer_id; }
		M.api.getJSONCb('ciniki.campaigns.campaignGet', args, function(rsp) {
			if( rsp.stat != 'ok' ) {
				M.api.err(rsp);
				return false;
			}
			var p = M.ciniki_campaigns_main.campaign;
			p.data = rsp.campaign;
			p.refresh();
			p.show(cb);
		});
	};
	this.campaign.customerAdd = function(cid) {
		this.show(null,null,cid);
	};
    this.campaign.addClose('Back');

    //
    // The panel for a site's menu
    //
    this.edit = new M.panel('Campaign',
        'ciniki_campaigns_main', 'edit',
        'mc', 'medium', 'sectioned', 'ciniki.campaigns.main.edit');
    this.edit.data = null;
    this.edit.campaign_id = 0;
    this.edit.sections = { 
        'details':{'label':'', 'fields':{
            'name':{'label':'Name', 'type':'text'},
            'status':{'label':'Status', 'type':'toggle', 'toggles':{'0':'Building', '10':'Active', '50':'Inactive', '60':'Deleted'}},
            'flags_1':{'label':'Specific Time', 'type':'flagtoggle', 'bit':0x01, 'field':'flags', 'default':'off', 'on_fields':['delivery_time']},
            'delivery_time':{'label':'Delivery Time', 'type':'text', 'size':'small', 'active':'yes', 
                'visible':function() { return (M.ciniki_campaigns_main.edit.data.flags&0x01)>0?'yes':'no'; }
                },
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_campaigns_main.edit.save();'},
            'delete':{'label':'Delete', 'fn':'M.ciniki_campaigns_main.campaign.remove(M.ciniki_campaigns_main.campaign.campaign_id);'},
            }},
        };
    this.edit.fieldValue = function(s, i, d) { return this.data[i]; }
    this.edit.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.campaigns.campaignHistory', 'args':{'business_id':M.curBusinessID, 'campaign_id':this.campaign_id, 'field':i}};
    }
    this.edit.edit = function(cb, cid) {
        if( cid != null ) { this.campaign_id = cid; }
        this.reset();
        this.sections._buttons.buttons.delete.visible = (this.campaign_id>0?'yes':'no');
        M.api.getJSONCb('ciniki.campaigns.campaignGet', {'business_id':M.curBusinessID, 'campaign_id':this.campaign_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_campaigns_main.edit;
            p.data = rsp.campaign;
            p.refresh();
            p.show(cb);
        });
    };
	this.edit.save = function() {
		var nv = this.formFieldValue(this.sections.details.fields.name, 'name');
		if( nv == '' ) {
			alert('You must specify a title');
			return false;
		}
		if( this.campaign_id > 0 ) {
			var c = this.serializeForm('no');
			if( c != '' ) {
				M.api.postJSONCb('ciniki.campaigns.campaignUpdate', {'business_id':M.curBusinessID, 'campaign_id':M.ciniki_campaigns_main.edit.campaign_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    } 
					M.ciniki_campaigns_main.edit.close();
                });
			} else {
				this.close();
			}
		} else {
			var c = this.serializeForm('yes');
			if( c != '' ) {
				M.api.postJSONCb('ciniki.campaigns.campaignAdd', {'business_id':M.curBusinessID}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    } 
                    if( rsp.id > 0 ) {
                        var cb = M.ciniki_campaigns_main.edit.cb;
                        M.ciniki_campaigns_main.edit.close();
                        M.ciniki_campaigns_main.campaign.show(cb,rsp.id);
                    } else {
                        M.ciniki_campaigns_main.edit.close();
                    }
                });
			} else {
				this.close();
			}
		}
	};
	this.edit.remove = function() {
		if( confirm("Are you sure you want to remove '" + this.data.name + "' as an campaign ?") ) {
			M.api.getJSONCb('ciniki.campaigns.campaignDelete', 
				{'business_id':M.curBusinessID, 'campaign_id':M.ciniki_campaigns_main.edit.campaign_id}, function(rsp) {
					if( rsp.stat != 'ok' ) {
						M.api.err(rsp);
						return false;
					}
					M.ciniki_campaigns_main.campaign.close();
				});
		}
	};
    this.edit.addButton('save', 'Save', 'M.ciniki_campaigns_main.edit.save();');
    this.edit.addClose('Cancel');

    //
    // The email edit panel
    //
    this.email = new M.panel('Campaign Email',
        'ciniki_campaigns_main', 'email',
        'mc', 'medium', 'sectioned', 'ciniki.campaigns.main.email');
    this.email.data = null;
    this.email.campaign_id = 0;
    this.email.email_id = 0;
    this.email.sections = { 
        'details':{'label':'', 'fields':{
            'subject':{'label':'Subject', 'type':'text'},
            'days_from_start':{'label':'Days from start', 'type':'text', 'size':'small'},
            'status':{'label':'Status', 'type':'toggle', 'toggles':{'10':'Active', '50':'Inactive', '60':'Deleted'}},
            'flags_1':{'label':'Specific Time', 'type':'flagtoggle', 'bit':0x01, 'field':'flags', 'default':'off', 'on_fields':['delivery_time']},
            'delivery_time':{'label':'Delivery Time', 'type':'text', 'size':'small', 'active':'yes', 
                'visible':function() { return (M.ciniki_campaigns_main.email.data.flags&0x01)>0?'yes':'no'; }
                },
            }},
        '_html_content':{'label':'Content', 'fields':{
            'html_content':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_campaigns_main.email.save();'},
            'delete':{'label':'Delete', 'fn':'M.ciniki_campaigns_main.email.remove(M.ciniki_campaigns_main.email.email_id);'},
            }},
        };
    this.email.fieldValue = function(s, i, d) { return this.data[i]; }
    this.email.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.campaigns.campaignEmailHistory', 'args':{'business_id':M.curBusinessID, 'email_id':this.email_id, 'field':i}};
    }
	this.email.edit = function(cb, eid, cid) {
		if( eid != null ) { this.email_id = eid; }
		if( cid != null ) { this.campaign_id = cid; }
		this.reset();
		this.sections._buttons.buttons.delete.visible = (this.email_id>0?'yes':'no');
		M.api.getJSONCb('ciniki.campaigns.campaignEmailGet', {'business_id':M.curBusinessID, 'email_id':this.email_id}, function(rsp) {
			if( rsp.stat != 'ok' ) {
				M.api.err(rsp);
				return false;
			}
			var p = M.ciniki_campaigns_main.email;
			p.data = rsp.email;
			p.refresh();
			p.show(cb);
		});
	};
	this.email.save = function() {
		var nv = this.formFieldValue(this.sections.details.fields.subject, 'subject');
		if( nv == '' ) {
			alert('You must specify a subject');
			return false;
		}
		if( this.email_id > 0 ) {
			var c = this.serializeForm('no');
			if( c != '' ) {
				M.api.postJSONCb('ciniki.campaigns.campaignEmailUpdate', 
					{'business_id':M.curBusinessID, 'email_id':M.ciniki_campaigns_main.email.email_id}, c,
					function(rsp) {
						if( rsp.stat != 'ok' ) {
							M.api.err(rsp);
							return false;
						} 
					M.ciniki_campaigns_main.email.close();
					});
			} else {
				this.close();
			}
		} else {
			var c = this.serializeForm('yes');
			if( c != '' ) {
				c += '&campaign_id=' + this.campaign_id;
				M.api.postJSONCb('ciniki.campaigns.campaignEmailAdd', 
					{'business_id':M.curBusinessID, 'campaign_id':M.ciniki_campaigns_main.email.campaign_id}, c, function(rsp) {
						if( rsp.stat != 'ok' ) {
							M.api.err(rsp);
							return false;
						} 
						M.ciniki_campaigns_main.email.close();
					});
			} else {
				this.close();
			}
		}
	};
	this.email.remove = function() {
		if( confirm("Are you sure you want to remove '" + this.data.subject + "' as an campaign email ?") ) {
			M.api.getJSONCb('ciniki.campaigns.campaignEmailDelete', 
				{'business_id':M.curBusinessID, 'email_id':M.ciniki_campaigns_main.email.email_id}, function(rsp) {
					if( rsp.stat != 'ok' ) {
						M.api.err(rsp);
						return false;
					}
					M.ciniki_campaigns_main.email.close();
				});
		}
	}
    this.email.addButton('save', 'Save', 'M.ciniki_campaigns_main.email.save();');
    this.email.addClose('Cancel');

	//
	// Arguments:
	// aG - The arguments to be parsed into args
	//
	this.start = function(cb, appPrefix, aG) {
		args = {};
		if( aG != null ) { args = eval(aG); }

		//
		// Create the app container if it doesn't exist, and clear it out
		// if it does exist.
		//
		var appContainer = M.createContainer(appPrefix, 'ciniki_campaigns_main', 'yes');
		if( appContainer == null ) {
			alert('App Error');
			return false;
		} 

		this.menu.open(cb);
	}
};
