<?php

namespace SimplyTestable\Integration\Tests;

use SimplyTestable\Integration\Tests\BaseTest;


class ResetTest extends BaseTest {    

    public function testPrepareEnvironment() {        
        if (getenv('SIMPLYTESTABLE_INTEGRATION_PREPARE')) {
            $this->clearEnvironmentLogs();
            $this->resetEnvironmentDatabases();          
            $this->requestWorkerActivation();
            $this->verifyWorkerActivation();            
        }
    }
    
    
    private function requestWorkerActivation() {
        foreach ($this->workers as $worker) {
            $this->runSymfonyCommand($worker, 'simplytestable:worker:activate');
        }
    }
    
    
    private function verifyWorkerActivation() {
        foreach ($this->workers as $workerIndex => $worker) {
            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:worker:activate:verify ' . ($workerIndex + 1));
        }        
    }    
    
    
    private function clearEnvironmentLogs() {
        foreach ($this->environments as $environment => $path) {
            $this->runCommand($environment, 'rm -Rf app/logs/*.log');
        }        
    }  
}