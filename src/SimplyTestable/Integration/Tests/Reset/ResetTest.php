<?php

namespace SimplyTestable\Integration\Tests;

use SimplyTestable\Integration\Tests\BaseTest;


class ResetTest extends BaseTest {    
    
    public static function setUpBeforeClass() {
        self::resetTestEnvironment();
    }

    public function testPrepareEnvironment() {        
        if (getenv('SIMPLYTESTABLE_INTEGRATION_PREPARE')) {
            $this->assertTrue(true);
        }
    }
 
}