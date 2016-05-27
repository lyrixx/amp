<?php

namespace Amp\Internal;

use Amp\Observable;

/**
 * An observable that cannot externally emit values. Used by Postponed in development mode.
 */
final class PrivateObservable implements Observable {
    use Producer;

    /**
     * @param callable(callable $emit, callable $complete, callable $fail): void $emitter
     */
    public function __construct(callable $emitter) {
        /**
         * Emits a value from the observable.
         *
         * @param mixed $value
         *
         * @return \Interop\Async\Awaitable
         */
        $emit = function ($value = null) {
            return $this->emit($value);
        };

        /**
         * Completes the observable with the given value.
         *
         * @param mixed $value
         *
         * @return \Interop\Async\Awaitable
         */
        $complete = function ($value = null) {
            return $this->complete($value);
        };

        /**
         * Fails the observable with the given exception.
         *
         * @param \Exception $reason
         *
         * @return \Interop\Async\Awaitable
         */
        $fail = function ($reason) {
            return $this->fail($reason);
        };

        try {
            $emitter($emit, $complete, $fail);
        } catch (\Throwable $exception) {
            $this->fail($exception);
        } catch (\Exception $exception) {
            $this->fail($exception);
        }
    }
}
