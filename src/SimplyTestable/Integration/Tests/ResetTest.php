<?php

namespace SimplyTestable\Integration\Tests;

use webignition\Http\Client\Client;


class ResetTest extends BaseTest {    

    public function testPrepareEnvironment() {        
        if (getenv('SIMPLYTESTABLE_INTEGRATION_PREPARE')) {
            $this->clearEnvironmentLogs();
            $this->resetEnvironmentDatabases();          
        }
    }
    
    
    private function clearEnvironmentLogs() {
        foreach ($this->environments as $environment => $path) {
            $this->runCommand($environment, 'rm -Rf app/logs/*.log');
        }        
    }
    
    
    private function resetEnvironmentDatabases() {
        foreach ($this->environments as $environment => $path) {
            $this->runSymfonyCommand($environment, 'doctrine:database:drop --force');
            $this->runSymfonyCommand($environment, 'doctrine:database:create');
            $this->runSymfonyCommand($environment, 'doctrine:migrations:migrate --no-interaction --quiet');
        }
    }    
}