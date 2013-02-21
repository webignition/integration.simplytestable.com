<?php

namespace SimplyTestable\Integration\Tests;

class StartAndCancelTestSequenceTest extends BaseTestSequenceTest { 
    

    public function testStartAndCancelTest() { 
        // Create and then cancel job
        $this->createJob();
        $this->cancelJob();

        // Verify cancellation response
        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
        
        // Verify job state
        $job = $this->getJob();
        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
        $this->assertEquals(self::$jobId, $job->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $job->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $job->website);
        $this->assertEquals('cancelled', $job->state);
        $this->assertEquals(0, $job->url_count);
        $this->assertEquals(0, $job->task_count);       
        $this->assertNotNull($job->time_period);
        $this->assertNotNull($job->time_period->start_date_time);
        $this->assertNotNull($job->time_period->end_date_time);
    }
      
}