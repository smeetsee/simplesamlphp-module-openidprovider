<?php

// Modified by Xander Smeets on 09-03-2024

if (isset($_SERVER['PATH_INFO'])) {
    $userId = substr($_SERVER['PATH_INFO'], 1);
} else {
    $userId = false;
}

$globalConfig = \SimpleSAML\Configuration::getInstance();
$server = \SimpleSAML\Module\openidprovider\ProviderServer::getInstance();
$identity = $server->getIdentity();

$httpUtils = new \SimpleSAML\Utils\HTTP;

if (!$userId && $identity) {
    /*
     * We are accessing the front-page, but are logged in.
     * Redirect to the correct page.
     */
    $httpUtils->redirectTrustedURL($identity);
}

// Determine whether we are at the users own page
if ($userId && $userId === $server->getUserId()) {
    $ownPage = true;
} else {
    $ownPage = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($ownPage) {
        foreach ($_POST as $k => $v) {
            $op = explode('_', $k, 2);
            if (count($op) == 1 || $op[0] !== 'remove') {
                continue;
            }

            $site = $op[1];
            $site = pack("H*", $site);
            $server->removeTrustRoot($identity, $site);
        }
    }

    $httpUtils->redirectTrustedURL($identity);
}

if ($ownPage) {
    $newSites = [];
    $trustedSites = $server->getTrustRoots($identity);
    foreach ($trustedSites as $i => $site) {
        $newSites[bin2hex($site)] = $site;
    }
    $trustedSites = $newSites;
} else {
    $trustedSites = [];
}

$userBase = \SimpleSAML\Module::getModuleURL('openidProvider/user.php');

$xrds = \SimpleSAML\Module::getModuleURL('openidProvider/xrds.php');
if ($userId !== false) {
    $xrds = $httpUtils->addURLParameters($xrds, array('user' => $userId));
}

header('X-XRDS-Location: ' . $xrds);

$as = $server->getAuthSource();
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'openidProvider:user.twig');
$t->data['identity'] = $identity;
$t->data['loggedInAs'] = $server->getUserId();
$t->data['loginURL'] = $as->getLoginURL($userBase);
$t->data['logoutURL'] = $as->getLogoutURL();
$t->data['ownPage'] = $ownPage;
$t->data['serverURL'] = $server->getServerURL();
$t->data['trustedSites'] = $trustedSites;
$t->data['userId'] = $userId;
$t->data['userIdURL'] = $userBase . '/' . $userId;

$t->send();
