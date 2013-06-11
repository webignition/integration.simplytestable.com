<?php

namespace SimplyTestable\Integration\Tests;

use webignition\Http\Client\Client;


abstract class BaseTest extends \PHPUnit_Framework_TestCase {      
    
    const HTTP_STATUS_OK = 200;
    const PUBLIC_USER_USERNAME = 'public';
    const PUBLIC_USER_PASSWORD = 'password';
    const TEST_CANONICAL_URL = 'http://webignition.net/';
    
    /**
     * Map of environment name to enviroment local path
     * 
     * @var array
     */
    protected static $environments = array(
        'ci.app.simplytestable.com' => '/www/ci.app.simplytestable.com',
        'hydrogen.ci.worker.simplytestable.com' => '/www/hydrogen.ci.worker.simplytestable.com',
        'lithium.ci.worker.simplytestable.com' => '/www/lithium.ci.worker.simplytestable.com'
    );
    
    
    /**
     * Names of environments that are workers
     * 
     * @var array
     */
    protected static $workers = array(
        'hydrogen.ci.worker.simplytestable.com',
        'lithium.ci.worker.simplytestable.com'
    );
    
    
    /**
     *
     * @var string
     */
    protected static $coreApplication = 'ci.app.simplytestable.com';
    
    
    /**
     *
     * @var \webignition\Http\Client\Client
     */
    private $httpClient;
    
    
    /**
     *
     * @param string $environment
     * @param string $symfonyCommand 
     */
    protected static function runSymfonyCommand($environment, $symfonyCommand) {
        return self::runCommand($environment, 'php app/console ' . $symfonyCommand);
    }
    
    
    /**
     *
     * @param string $environment
     * @param string $command 
     */
    protected static function runCommand($environment, $command) {        
        return shell_exec('cd ' . self::$environments[$environment] . ' && ' . $command);
    }
    
    
    /**
     *
     * @return \HttpRequest 
     */
    protected function getAuthorisedHttpRequest($url = '', $request_method = HTTP_METH_GET, $options = array()) {
        $httpRequest = new \HttpRequest($url, $request_method, $options);
        $httpRequest->addHeaders(array(
            'Authorization' => 'Basic ' . base64_encode('public:public')
        ));
        
        return $httpRequest;
    }      
    
    
    /**
     *
     * @return \HttpRequest 
     */
    protected function getWorkerAdminHttpRequest($url = '', $request_method = HTTP_METH_GET, $options = array()) {
        $httpRequest = new \HttpRequest($url, $request_method, $options);
        $httpRequest->addHeaders(array(
            'Authorization' => 'Basic ' . base64_encode('admin:adminpassword')
        ));
        
        return $httpRequest;
    }  
    
    
    /**
     *
     * @return \webignition\Http\Client\Client
     */
    protected function getHttpClient() {
        if (is_null($this->httpClient)) {
            $this->httpClient = new \webignition\Http\Client\Client();
            $this->httpClient->redirectHandler()->enable();
        }
        
        return $this->httpClient;
    }
    
    protected static function resetTestEnvironment() {
        self::clearEnvironmentLogs();
        self::resetEnvironmentDatabases();          
        self::requestWorkerActivation();
        self::verifyWorkerActivation();        
    }
    
    protected static function resetEnvironmentDatabases() {
        self::resetCoreApplicationDatabase();
        self::resetWorkerDatabases();
    }
    

    protected static function resetCoreApplicationDatabase() {    
        self::resetDatabase(self::$coreApplication);
        self::runSymfonyCommand(self::$coreApplication, 'doctrine:fixtures:load --append');
    }    
    
    
    protected static function resetWorkerDatabases() {
        foreach (self::$workers as $environment) {
            self::resetDatabase($environment);
        }
    }    
    
    private static function resetDatabase($environment) {
        self::runSymfonyCommand($environment, 'doctrine:database:drop --force');
        self::runSymfonyCommand($environment, 'doctrine:database:create');
        self::runSymfonyCommand($environment, 'doctrine:migrations:migrate --no-interaction --quiet');          
    }
    
    
    protected static function requestWorkerActivation() {
        foreach (self::$workers as $worker) {
            self::runSymfonyCommand($worker, 'simplytestable:worker:activate');
        }
    }
    
    
    protected static function verifyWorkerActivation() {
        foreach (self::$workers as $workerIndex => $worker) {
            self::runSymfonyCommand(self::$coreApplication, 'simplytestable:worker:activate:verify ' . ($workerIndex + 1));
        }        
    }    
    
    
    protected static function clearEnvironmentLogs() {
        foreach (self::$environments as $environment => $path) {
            self::runCommand($environment, 'rm -Rf app/logs/*.log');
        }        
    } 

    protected static function startRedis() {
        return shell_exec('sudo service redis-server start');
    } 
    
    protected static function clearRedis() {
        self::startRedis();
        shell_exec('redis-cli -r 1 flushall');        
    }
    
    
    protected static function stopRedis() {
        return shell_exec('sudo service redis-server stop');
    }
    
      
}