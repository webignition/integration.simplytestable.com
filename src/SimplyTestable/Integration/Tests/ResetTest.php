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
}