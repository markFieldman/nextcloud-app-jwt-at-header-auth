<?php

namespace OCA\JwtAuth\Controller;

use OC_Util;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Controller;
use \OCP\ILogger;
use function mysql_xdevapi\getSession;

class LoginController extends Controller
{

    /**
     * @var \OCP\IConfig
     */
    private $config;

    /**
     * @var \OC\User\Manager
     */
    private $userManager;

    /**
     * @var \OC\User\Session
     */
    private $session;

    /**
     * @var \OCA\JwtAuth\Helper\LoginChain
     */
    private $loginChain;

    private $logger;

    /**
     * @var \OCA\JwtAuth\Helper\JwtAuthTokenParser
     */
    private $jwtAuthTokenParser;

    public function __construct(
        $AppName,
        ILogger $logger,
        \OCP\IRequest $request,
        \OCP\IConfig $config,
        \OC\User\Session $session,
        \OC\User\Manager $userManager,
        \OCA\JwtAuth\Helper\LoginChain $loginChain,
        \OCA\JwtAuth\Helper\JwtAuthTokenParser $jwtAuthTokenParser
    )
    {
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->config = $config;
        $this->session = $session;
        $this->userManager = $userManager;
        $this->loginChain = $loginChain;
        $this->jwtAuthTokenParser = $jwtAuthTokenParser;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function auth()
    {
        $tokenHeader = $this->getRawTokenFromRequestHeader();
        if (is_null($tokenHeader)) {
            return new RedirectResponse(OC_Util::getDefaultPageUrl());
        }
        $username = $this->getUsernameFromTokenInHeader($tokenHeader);
	if (is_null($username)) {
            // It could be that the JWT token has expired.
            // Redirect to the homepage, which likely redirects to /login
            // and starts the whole flow over again.
            // Hopefully we have better luck next time.
            return new RedirectResponse('/');
        }
        $redirectUrl = '/';
        $user = $this->userManager->get($username);
        if ($user === null) {
            // This could be made friendlier.
            die('Tried to log in with a user which does not exist.');
        }

        if ($this->session->getUser() === $user) {
            // Already logged in. No need to log in once again.
            return new RedirectResponse($redirectUrl);
        }

        if ($this->session->getUser() !== null) {
            // If there is an old session, it would cause our login attempt to not work.
            // We'd be setting some session cookies, but other old ones would remain
            // and the old session would be in use.
            // We work around this by destroying the old session before proceeding.
            $this->session->logout();
        }

        $loginData = new \OC\Authentication\Login\LoginData(
            $this->request,
            $username,
            // Password. It doesn't matter because our custom Login chain
            // doesn't validate it at all.
            '',
            $redirectUrl,
            '', // Timezone
            '', // Timezone offset
        );

        // Prepopulate the login request with the user we're logging in.
        // This usually happens in one of the steps of the default LoginChain.
        // For our custom login chain, we pre-populate it.
        $loginData->setUser($user);
        $this->session->getSession()->set('last-password-confirm', time());
        $this->config->deleteUserValue($user->getUID(), 'core', 'lostpassword');
        // This is expected to log the user in, updating the session, etc.
        $result = $this->loginChain->process($loginData);
        if (!$result->isSuccess()) {
            // We don't expect any failures, but who knows..
            die('Internal login failure');
        }

        return new RedirectResponse($redirectUrl);
    }

    private function getRawTokenFromRequestHeader()
    {
        $headers = getallheaders();
        $bearer_header = $headers['Authorization'];
        if (!is_null($bearer_header)) {
            $header_value = substr($bearer_header, strpos($bearer_header, " ") + 1);
            return $header_value;
        } else {
            return null;
        }
    }

    /**
     * Extract publicKeyPath from config.php
     * @return string
     */
    public function getPublicKeyPath(): string
    {
        return $this->config->getSystemValue("jwt.publicKey");
    }

    /**
     * Extract algorithm of JWT sign from config.php
     * @return string
     */
    public function getJwtAlg(): string
    {
        return $this->config->getSystemValue("jwt.alg");
    }

    /**
     * @param $tokenHeader
     * @return string|null
     */
    public function getUsernameFromTokenInHeader($tokenHeader): ?string
    {
        $jwtPublicKeyPath = $this->getPublicKeyPath();
        $jwtAlg = $this->getJwtAlg();
        return $this->jwtAuthTokenParser->parseValidatedToken($tokenHeader, $jwtPublicKeyPath, $jwtAlg);
    }

}
