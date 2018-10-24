<?php

// include roundcube
if(!defined('INSTALL_PATH')) {
    define('INSTALL_PATH', RCMAIL_INSTALL_PATH . '/');
}
require_once INSTALL_PATH . 'program/include/iniset.php';

// init application, start session, init output class, etc.
$RCMAIL = rcmail::get_instance($GLOBALS['env']);

// check if config files had errors
if ($err_str = $RCMAIL->config->get_error()) {
    rcmail::raise_error(array(
        'code' => 601,
        'type' => 'php',
        'message' => $err_str), false, true);
}

// check DB connections and exit on failure
if ($err_str = $RCMAIL->db->is_error()) {
    rcmail::raise_error(array(
        'code' => 603,
        'type' => 'db',
        'message' => $err_str), FALSE, TRUE);
}

// error steps
if ($RCMAIL->action == 'error' && !empty($_GET['_code'])) {
    rcmail::raise_error(array('code' => hexdec($_GET['_code'])), FALSE, TRUE);
}

// trigger startup plugin hook
$startup = $RCMAIL->plugins->exec_hook('startup',
                                       array('task' => $RCMAIL->task,
                                             'action' => $RCMAIL->action));
$RCMAIL->set_task($startup['task']);
$RCMAIL->action = $startup['action'];

// Autoloader
require_once SABREDAV_INSTALL_DIR . '/vendor/autoload.php';

// load backend classes
require_once __DIR__ . '/auth_backend.php';
require_once __DIR__ . '/principal_backend.php';
require_once __DIR__ . '/carddav_backend.php';

// Backends
$authBackend      = new sabrecube_auth($RCMAIL);
$principalBackend = new sabrecube_principals($RCMAIL);
$carddavBackend   = new sabrecube_carddav($RCMAIL);
$calendarBackend  = new Sabre\CalDAV\Backend\PDO(new PDO('sqlite:' . RCMAIL_INSTANCE_DIR . '/calendars.sqlite'));

// Setting up the directory tree
$nodes = [
    new Sabre\DAVACL\PrincipalCollection($principalBackend),
    new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
    new Sabre\CalDAV\CalendarRoot($principalBackend, $calendarBackend),
];

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);
$server->setBaseUri(SABRECUBE_BASE_URI);

// Plugins
$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend, 'SabreDAV'));
$server->addPlugin(new Sabre\DAV\Browser\Plugin());
$server->addPlugin(new Sabre\DAVACL\Plugin());

$server->addPlugin(new Sabre\CardDAV\Plugin());
$server->addPlugin(new Sabre\CalDAV\Plugin());
$server->addPlugin(new Sabre\CalDAV\Subscriptions\Plugin());
$server->addPlugin(new Sabre\CalDAV\Schedule\Plugin());

$server->addPlugin(new Sabre\DAV\Sync\Plugin());

// And off we go!
$server->exec();

?>
