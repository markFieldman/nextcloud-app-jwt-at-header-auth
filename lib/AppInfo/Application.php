<?php
namespace OCA\JwtAuth\AppInfo;

use \OCP\AppFramework\App;

class Application extends App {

	public function __construct(array $urlParams = array()) {
		parent::__construct('jwtauth', $urlParams);

		$container = $this->getContainer();

		$container->registerService('jwtAuthTokenParser', function ($c) {
            		return new \OCA\JwtAuth\Helper\JwtAuthTokenParser();
		});

		$container->registerService('loginPageInterceptor', function ($c) {
			return new \OCA\JwtAuth\Helper\LoginPageInterceptor(
				$c->query(\OC\User\Session::class),
			);
		});
	}

}
