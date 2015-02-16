<?php
namespace FutureProcess;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class FutureValue
{
    private $waitFn;
    private $deferred;
    private $isRealised = false;
    private $value;
    private $error;
    
    public function __construct($waitFn)
    {
        $this->waitFn = $waitFn;
        $this->deferred = new Deferred;
    }
    
    /**
     * @return bool
     */
    public function isRealised()
    {
        return $this->isRealised;
    }
    
    /**
     * @param mixed $value OPTIONAL
     */
    public function resolve($value = null)
    {
        if (!$this->isRealised) {
            $this->value = $value;
            $this->isRealised = true;
            $this->deferred->resolve($value);
        }
    }
    
    /**
     * @param \Exception $e
     */
    public function reject(\Exception $e)
    {
        if (!$this->isRealised) {
            $this->error = $e;
            $this->isRealised = true;
            $this->deferred->reject($e);
        }
    }
    
    /**
     * @param double $timeout OPTIONAL
     * @return mixed
     */
    public function getValue($timeout = null)
    {
        $this->wait($timeout);
        
        if ($this->error) {
            throw $this->error;
        }
        
        return $this->value;
    }

    /**
     * @param double $timeout
     * @return static
     */
    public function wait($timeout = null)
    {
        if (!$this->isRealised) {
            call_user_func($this->waitFn, $timeout, $this);
        }
        
        return $this;
    }
    
    /**
     * @param callable $onFulfilled
     * @param callable $onError
     * @param callable $onProgress
     * @return PromiseInterface
     */
    public function then($onFulfilled = null, $onError = null, $onProgress = null)
    {
        return $this->deferred->promise()->then($onFulfilled, $onError, $onProgress);
    }
}
