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
    protected function runSymfonyCommand($environment, $symfonyCommand) {
        $this->runCommand($environment, 'php app/console ' . $symfonyCommand);
    }
    
    
    /**
     *
     * @param string $environment
     * @param string $command 
     */
    protected function runCommand($environment, $command) {        
        passthru('cd ' . self::$environments[$environment] . ' && ' . $command);
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
     * @return \webignition\Http\Client\Client
     */
    protected function getHttpClient() {
        if (is_null($this->httpClient)) {
            $this->httpClient = new \webignition\Http\Client\Client();
            $this->httpClient->redirectHandler()->enable();
        }
        
        return $this->httpClient;
    }  
    
    protected static function resetEnvironmentDatabases() {
        self::resetCoreApplicationDatabase();
        self::resetWorkerDatabases();
    }
    

    protected static function resetCoreApplicationDatabase() {    
        self::resetDatabase(self::$coreApplication);
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
    
      
}