<?php
namespace FutureProcess;

use Symfony\Component\Process\PhpExecutableFinder;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    private $phpExecutablePath;
    
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        
        $finder = new PhpExecutableFinder;
        $this->phpExecutablePath = $finder->find();
    }
    
    public function testPHPHelloWorld()
    {
        $shell = new Shell;
        $command = "{$this->phpExecutablePath} -r \"echo 'Hello World';\"";
        $result = $shell->startProcess($command)->getResult()->wait(2);
        
        $this->assertSame(0, $result->getExitCode(), $result->readFromBuffer(2));
        $this->assertSame('Hello World', $result->readFromBuffer(1));
    }
    
    public function testExecuteCommandWithTimeout()
    {
        $shell = new Shell;
        $command = $this->phpSleepCommand(0.1);
        
        $startTime = microtime(true);
        try {
            $shell->startProcess($command)->getResult()->wait(0.05);
            $this->fail('Expected TimeoutException was not thrown');
        } catch (TimeoutException $e) {
            $runTime = microtime(true) - $startTime;
            $this->assertGreaterThanOrEqual(0.05, $runTime);
        }
        
        $result = $shell->startProcess($command)->getResult()->wait(0.5);
        $this->assertSame(0, $result->getExitCode());
    }
    
    public function testQueue()
    {
        $shell = new Shell;
        $shell->setProcessLimit(2);
        
        $process1 = $shell->startProcess($this->phpSleepCommand(0.5));
        $process2 = $shell->startProcess($this->phpSleepCommand(0.5));
        $process3 = $shell->startProcess($this->phpSleepCommand(0.5));
        
        usleep(100000);
        
        $this->assertSame(FutureProcess::STATUS_RUNNING, $process1->getStatus());
        $this->assertSame(FutureProcess::STATUS_RUNNING, $process2->getStatus());
        $this->assertSame(FutureProcess::STATUS_QUEUED, $process3->getStatus());
        
        $this->assertSame(FutureProcess::STATUS_RUNNING, $process3->wait(1)->getStatus());
    }
    
    public function testGetPid()
    {
        $shell = new Shell;
        
        $process = $shell->startProcess("{$this->phpExecutablePath} -r \"echo getmypid();\"");
        
        $reportedPid = $process->getPid();
        
        $actualPid = (int)$process->getResult()->readFromBuffer(1);
        
        $this->assertSame($actualPid, $reportedPid);
    }
    
    public function testLateStreamResolution()
    {
        $shell = new Shell;
        
        $result = $shell->startProcess("{$this->phpExecutablePath} -r \"echo 'hello';\"")
            ->getResult();
        
        $output = null;
        $result->then(function ($result) use (&$output) {
            $output = $result->readFromBuffer(1);
        });
        
        $result->wait(2);
        
        $this->assertSame('hello', $output);
    }
    
    public function testBufferFill()
    {
        $shell = new Shell;

        $result = $shell->startProcess("php -r \"echo str_repeat('x', 100000);\"")
            ->getResult();

        try {
            $result->wait(0.5);
        } catch (TimeoutException $e) {
            $this->fail('The child process is blocked. The output buffer is probably full.');
        }

        $this->assertSame(100000, strlen($result->readFromBuffer(1)));
    }
    
    public function testRepeatedReadCalls()
    {
        $shell = new Shell;
        $command = "{$this->phpExecutablePath} -r \"echo 'Hello World';\"";
        $result = $shell->startProcess($command)->getResult()->wait(2);
        
        $this->assertSame(0, $result->getExitCode(), $result->readFromBuffer(2));
        $this->assertSame('Hello World', $result->readFromBuffer(1));
        $this->assertSame('', $result->readFromBuffer(1));
    }
    
    private function phpSleepCommand($seconds)
    {
        $microSeconds = $seconds * 1000000;
        
        return "{$this->phpExecutablePath} -r \"usleep($microSeconds);\"";
    }
}
