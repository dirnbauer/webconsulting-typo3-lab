#
# Only columns that need a larger type or an explicit index beyond the
# TCA-generated defaults (uid/pid/tstamp/crdate/deleted are generated).
#

CREATE TABLE tx_flue_flow (
    identifier varchar(64) DEFAULT '' NOT NULL,
    workflow_name varchar(190) DEFAULT '' NOT NULL,
    default_agent varchar(190) DEFAULT '' NOT NULL,
    default_model varchar(190) DEFAULT '' NOT NULL,
    skills text,
    mcp_tools text,
    input_schema mediumtext,
    instructions text,
    UNIQUE KEY identifier (identifier)
);

CREATE TABLE tx_flue_run (
    flow int(11) unsigned DEFAULT '0' NOT NULL,
    be_user int(11) unsigned DEFAULT '0' NOT NULL,
    run_key varchar(64) DEFAULT '' NOT NULL,
    flue_run_id varchar(64) DEFAULT '' NOT NULL,
    target_table varchar(255) DEFAULT '' NOT NULL,
    target_uid int(11) unsigned DEFAULT '0' NOT NULL,
    workspace_uid int(11) unsigned DEFAULT '0' NOT NULL,
    instructions text,
    status varchar(20) DEFAULT 'idle' NOT NULL,
    payload mediumtext,
    events mediumtext,
    output mediumtext,
    usage_json mediumtext,
    result_json mediumtext,
    verdict varchar(32) DEFAULT '' NOT NULL,
    error_message text,
    started int(11) unsigned DEFAULT '0' NOT NULL,
    finished int(11) unsigned DEFAULT '0' NOT NULL,
    KEY flow_status (flow, status),
    KEY be_user_status (be_user, status, deleted),
    KEY run_key (run_key, status)
);
