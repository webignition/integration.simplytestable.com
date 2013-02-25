<?php

namespace SimplyTestable\Integration\Tests\Run;

use SimplyTestable\Integration\Tests\BaseTest;

abstract class BaseTestSequenceTest extends BaseTest {
    
    /**
     *
     * @var int
     */
    protected static $jobId = null;  
    
    
    /**
     *
     * @var \HttpResponse
     */
    protected static $lastHttpResponse = null;    
    
    
    public static function setUpBeforeClass() {
        self::resetTestEnvironment();
    }    
    
    
    /**
     * Get current job properties
     * 
     * @return stdClass
     */
    protected function getJob() {
        if (is_null(self::$jobId)) {
            $this->startJob();
        }
        
        $request = $this->getAuthorisedHttpRequest('http://'.self::$coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/');        
        return $this->retrieveObjectViaHttp($request);   
    }
    
    
    /**
     * Get collection of current job tasks
     * 
     * @return array
     */
    protected function getTasks() {
        $request = $this->getAuthorisedHttpRequest('http://'.self::$coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/tasks/');        
        return $this->retrieveObjectViaHttp($request);          
    }
    
    
    /**
     * 
     * @param \HttpRequest $request
     * @return stdClass|array
     */
    protected function retrieveObjectViaHttp(\HttpRequest $request) {
        self::$lastHttpResponse = $this->getHttpClient()->getResponse($request);        
        return json_decode(self::$lastHttpResponse->getBody());           
    }    
    
    
    protected function startJob() {
        // Request job to be started
        $request = $this->getAuthorisedHttpRequest('http://'.self::$coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/start/');        
        $this->getHttpClient()->redirectHandler()->enable();
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody()); 
        
        // Verify results
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertInternalType('integer', $responseObject->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $responseObject->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $responseObject->website);
        $this->assertEquals('new', $responseObject->state);
        $this->assertEquals(0, $responseObject->url_count);
        $this->assertEquals(0, $responseObject->task_count);
        
        self::$jobId = $responseObject->id;        
    }
    
    
    protected function cancelJob() {
        $cancelRequest = $this->getAuthorisedHttpRequest('http://'.self::$coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId.'/cancel/');        
        $this->retrieveObjectViaHttp($cancelRequest);        
    }
    
    
    protected function prepareJob() {
        self::runSymfonyCommand(self::$coreApplication, 'simplytestable:job:prepare ' . self::$jobId);
    }
    
}