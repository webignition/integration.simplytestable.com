Integration test scripts for simplytestable.com
===============================================

 A slight misuse of PHPUnit unit tests to enable the sequential modification of a stateful integration environment.

There's a [single unit test](https://github.com/webignition/integration.simplytestable.com/blob/master/src/SimplyTestable/Integration/Tests/IntegrationTest.php)
with a sequence of test methods each @dependent on the one before.

The environment comprises:

- an instance of the [core application](https://github.com/webignition/app.simplytestable.com) running at `http://ci.app.simplytestable.com`
- <div>two [workers](https://github.com/webignition/worker.simplytestable.com) instances running at:
  - `http://hydrogen.ci.worker.simplytestable.com`
  - `http://lithium.ci.worker.simplytestable.com`
</div>

Test Sequence
-------------

The presentation of the adventure in the unit tests is a little dry. Here's what's going on:

1. <div><strong>Prepare Test Environment</strong>
  - Drop, create and migrate the database for the core application and all workers
  - Have each worker request activation with the core application
  - Have the core application verify the activation request for each worker
<br />
</div>
2. <div><strong>Start a test for `http://webignition.net/`</strong>
  - GET `http://ci.app.simplytestable.com/tests/http://webignition.net/start/`
  - Assert that the response JSON contains all we'd hope for
<br />
</div>

3. <div><strong>Prepare the new test</strong>
  - Expand the test job for `http://webignition.net/` into a set of tasks
<br />
</div>

4. <div><strong>Check the test job status prior to tasks being assigned to workers</strong>
  - Assert that the job that was previously 'new' is now 'queued'
  - Assert that the job has a non-zero-length collection of tasks
  - Assert that each task has a status of 'queued'
<br />
</div>

5. <div><strong>Assign tasks to workers</strong>
  - Dish out each task to a worker
<br />
</div>

6.  <div><strong>Check the test job status after being assigned to workers</strong>
  - Assert that the job that was previously 'queued' is now 'in-progress'
  - Assert that the job has a start date
  - Assert that the job has a non-zero-length collection of tasks
  - Assert that each task has a status of 'in-progress' and has a start date
<br />
</div>