<?php

namespace OCA\OnedriveEncryption\Middleware;

use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\Response;
use OCA\Onedrive\Controller\ConfigController;
use OCA\Onedrive\Controller\OnedriveAPIController;
use OCA\Onedrive\Service\UserScopeService;
use OCA\Onedrive\Service\OnedriveAPIService;
use OCA\Onedrive\Service\OnedriveStorageAPIService;
use OCA\Onedrive\AppInfo\Application as OnedriveApplication;
use OCA\OnedriveEncryption\Service\OnedriveStorageAPIServiceCustom;
use OC;

class OnedriveEncryptionMiddleware extends Middleware {
	public function __construct() {
	}

	public function beforeOutput($controller, $methodName, $output){
		return $output;
	}

	public function beforeController($controller, $methodName) {
		if (! ($controller instanceof OnedriveAPIController && $methodName == 'importOnedrive')) {
			return;
		}

		$userId = OC::$server->getUserSession()->getUser()->getUID();
		$loggerInterface = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$appId = OnedriveApplication::APP_ID;
		$logger = new OC\AppFramework\ScopedPsrLogger($loggerInterface, $appId);
		
		$root = OC::$server->getLazyRootFolder();
		$config = OC::$server->getConfig();
		$jobList = OC::$server->getJobList();
		$userScopeService = OC::$server->get(UserScopeService::class);
		$userScopeService->setUserScope($userId);
		$userScopeService->setFilesystemScope($userId);

		$onedriveApiService = OC::$server->get(OnedriveAPIService::class);
		$service = new OnedriveStorageAPIServiceCustom($appId, $logger, $root, $config, $jobList, $userScopeService, $onedriveApiService);
		$response = $service->startImportOnedrive($userId);

		ob_start();
		echo json_encode($response);
		$size = ob_get_length();
		header("Content-Encoding: none");
		header("Content-Length: {$size}");
		header("Connection: close");
		ob_end_flush();
		flush();

		if (session_id()) {
			session_write_close();
		}

		$service->importOnedriveJob($userId);
		exit();
	}
	public function afterController($controller, $methodName, Response $response): Response {
		return $response;
	}
}